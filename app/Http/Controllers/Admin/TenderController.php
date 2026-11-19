<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Category;
use App\Models\Industry;
use App\Models\Province;
use App\Models\Tender;
use App\Models\TenderDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tenders are entered here by the data team: collected from the buyers and the tender sites,
 * written as a draft, then published. Publishing puts a tender on the site and in the alerts.
 */
class TenderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only('q', 'industry', 'province', 'type', 'buyer');
        $status = isset(Tender::STATUSES[$request->query('status')]) ? $request->query('status') : null;

        return view('admin.tenders.index', [
            'filters' => $filters,
            'status' => $status,
            'rows' => Tender::filtered($filters)->when($status, fn (Builder $q) => $q->where('status', $status))
                ->with('industry', 'province')->withCount('documents')->orderByDesc('id')->paginate(25)->withQueryString(),
            'industries' => Industry::options(),
            'counts' => Tender::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.tenders.form', $this->lists() + ['tender' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Tender::freeSlug($data['title']);
        $data['admin_id'] = auth('admin')->id();
        $tender = Tender::create($this->prepare($data, null));
        $this->addDocuments($tender, $request);
        AdminLog::record('added tender', $tender->title, $tender->status);

        return redirect()->route('admin.tenders.edit', ['id' => $tender->id])->with('success', $tender->status === Tender::PUBLISHED ? __('The tender is published.') : __('The tender is saved as a draft.'));
    }

    public function edit(int $id): View
    {
        return view('admin.tenders.form', $this->lists() + ['tender' => Tender::with('documents')->findOrFail($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tender = Tender::findOrFail($id);
        $before = $tender->status;
        $tender->update($this->prepare($this->validated($request), $tender));
        $this->addDocuments($tender, $request);
        AdminLog::record('changed tender', $tender->title, $before !== $tender->status ? 'status: '.$before.' → '.$tender->status : null);

        return back()->with('success', __('The tender is saved.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $tender = Tender::with('documents')->findOrFail($id);
        if ($tender->alerted_at) {
            throw new UserError(__('Alerts about this tender were already sent. Cancel it instead of deleting it.'));
        }
        foreach ($tender->documents as $document) {
            Storage::disk('local')->delete($document->path);
        }
        AdminLog::record('deleted tender', $tender->title);
        $tender->delete();

        return redirect()->route('admin.tenders.index')->with('success', __('The tender is deleted.'));
    }

    public function document(int $id, int $document): StreamedResponse
    {
        $file = TenderDocument::where('tender_id', $id)->findOrFail($document);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->name);
    }

    public function destroyDocument(int $id, int $document): RedirectResponse
    {
        $file = TenderDocument::where('tender_id', $id)->findOrFail($document);
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', __('The document is removed.'));
    }

    private function lists(): array
    {
        return [
            'industries' => Industry::options(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'industry_id', 'name']),
            'provinces' => Province::options(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(config('tenders.types')))],
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_type' => ['required', Rule::in(array_keys(config('tenders.buyer_types')))],
            'industry_id' => ['required', 'exists:industries,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:60000'],
            'eligibility' => ['nullable', 'string', 'max:10000'],
            'submission_method' => ['nullable', Rule::in(array_keys(config('tenders.submission_methods')))],
            'submission_details' => ['nullable', 'string', 'max:5000'],
            'contact' => ['nullable', 'string', 'max:2000'],
            'budget_type' => ['required', Rule::in(['exact', 'range', 'undisclosed'])],
            'budget_min' => ['nullable', 'required_unless:budget_type,undisclosed', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'required_if:budget_type,range', 'numeric', 'gte:budget_min'],
            'budget_currency' => ['nullable', 'required_unless:budget_type,undisclosed', Rule::in(config('tenders.currencies'))],
            'size_band' => ['nullable', Rule::in(array_keys(config('tenders.size_bands')))],
            'published_on' => ['nullable', 'date'],
            'questions_until' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(Tender::STATUSES))],
            'awarded_to' => ['nullable', 'string', 'max:255'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,zip,jpg,jpeg,png', 'max:20480'],
        ], [], ['budget_min' => __('budget'), 'budget_max' => __('highest budget'), 'industry_id' => __('sector')]);
    }

    /** Tidy what the form sent before it is stored. */
    private function prepare(array $data, ?Tender $tender): array
    {
        unset($data['documents']);
        if ($data['budget_type'] === 'undisclosed') {
            $data['budget_min'] = $data['budget_max'] = $data['budget_currency'] = null;
        } elseif ($data['budget_type'] === 'exact') {
            $data['budget_max'] = null;
        }
        // the category must belong to the chosen sector
        if (! empty($data['category_id']) && ! Category::where('id', $data['category_id'])->where('industry_id', $data['industry_id'])->exists()) {
            $data['category_id'] = null;
        }
        // a tender gets its publishing day the first time it is published
        if ($data['status'] === Tender::PUBLISHED && empty($data['published_on'])) {
            $data['published_on'] = $tender?->published_on?->toDateString() ?? Tender::now()->toDateString();
        }
        if ($data['status'] !== Tender::AWARDED) {
            $data['awarded_to'] = $data['awarded_to'] ?? null;
        }

        return $data;
    }

    private function addDocuments(Tender $tender, Request $request): void
    {
        foreach ($request->file('documents', []) as $file) {
            TenderDocument::create([
                'tender_id' => $tender->id,
                'name' => mb_substr($file->getClientOriginalName(), 0, 250),
                'path' => $file->store('tenders/'.$tender->id, 'local'),
                'size' => (int) $file->getSize(),
            ]);
        }
    }
}

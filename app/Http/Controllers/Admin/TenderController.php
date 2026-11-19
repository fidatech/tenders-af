<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Mail\BusinessMail;
use App\Models\AdminLog;
use App\Models\Category;
use App\Models\Industry;
use App\Models\Notice;
use App\Models\Province;
use App\Models\Tender;
use App\Models\TenderDocument;
use App\Models\User;
use App\Support\TenderForm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Tenders are entered here by the data team, and tenders posted by buyers wait here to be
 * approved. Publishing puts a tender on the site and in the alerts.
 */
class TenderController extends Controller
{
    /** Why a tender posted by a buyer can be sent back. The administrator picks one; the buyer reads the text. */
    public const REASONS = [
        'incomplete' => 'Important details are missing: please add the scope, the deadline and how to submit.',
        'no_documents' => 'The tender documents are missing or cannot be opened.',
        'duplicate' => 'This tender is already on the site.',
        'not_tender' => 'This is not a tender. Only tenders, quotations and calls for bids are published.',
        'wrong_sector' => 'The sector or the province is wrong. Please choose the right ones.',
        'unclear' => 'The text is not clear enough to publish. Please rewrite the summary.',
        'payment' => 'A tender must not ask bidders to pay in order to take part.',
    ];

    public function index(Request $request): View
    {
        $filters = $request->only('q', 'industry', 'province', 'type', 'buyer');
        $status = isset(Tender::STATUSES[$request->query('status')]) ? $request->query('status') : null;

        return view('admin.tenders.index', [
            'filters' => $filters,
            'status' => $status,
            'rows' => Tender::filtered($filters)->when($status, fn (Builder $q) => $q->where('status', $status))
                ->with('industry', 'province', 'company')->withCount('documents')->orderByDesc('id')->paginate(25)->withQueryString(),
            'industries' => Industry::options(),
            'counts' => Tender::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.tenders.form', $this->lists() + ['tender' => null, 'reasons' => self::REASONS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Tender::freeSlug($data['title']);
        $data['admin_id'] = auth('admin')->id();
        $tender = Tender::create(TenderForm::prepare($data, null));
        TenderForm::addDocuments($tender, $request);
        AdminLog::record('added tender', $tender->title, $tender->status);

        return redirect()->route('admin.tenders.edit', ['id' => $tender->id])->with('success', $tender->status === Tender::PUBLISHED ? __('The tender is published.') : __('The tender is saved as a draft.'));
    }

    public function edit(int $id): View
    {
        return view('admin.tenders.form', $this->lists() + ['tender' => Tender::with('documents', 'company')->findOrFail($id), 'reasons' => self::REASONS]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tender = Tender::findOrFail($id);
        $before = $tender->status;
        $tender->update(TenderForm::prepare($this->validated($request), $tender) + ['reject_reason' => null]);
        TenderForm::addDocuments($tender, $request);
        AdminLog::record('changed tender', $tender->title, $before !== $tender->status ? 'status: '.$before.' → '.$tender->status : null);
        // a tender posted by a buyer was waiting, and is published now: the buyer is told
        if ($before === Tender::PENDING && $tender->status === Tender::PUBLISHED) {
            $this->tellBuyer($tender, 'Your tender is published', ['It is on the site now, and the companies of its sector are being told.']);
        }

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
        return $request->validate(TenderForm::rules() + [
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_type' => ['required', Rule::in(array_keys(config('tenders.buyer_types')))],
            'size_band' => ['nullable', Rule::in(array_keys(config('tenders.size_bands')))],
            'published_on' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(Tender::STATUSES))],
            'awarded_to' => ['nullable', 'string', 'max:255'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:500'],
        ], [], TenderForm::names());
    }

    /** Publish a tender a buyer posted. It goes on the site and into the next round of alerts. */
    public function approve(int $id): RedirectResponse
    {
        $tender = Tender::where('status', Tender::PENDING)->findOrFail($id);
        $tender->update(['status' => Tender::PUBLISHED, 'reject_reason' => null, 'admin_id' => auth('admin')->id(), 'published_on' => $tender->published_on ?? Tender::now()->toDateString()]);
        AdminLog::record('approved tender', $tender->title, $tender->company?->name);
        $this->tellBuyer($tender, 'Your tender is published', ['It is on the site now, and the companies of its sector are being told.']);

        return redirect()->route('admin.tenders.index', ['status' => Tender::PENDING])->with('success', __('The tender is published.'));
    }

    /** Send a tender back to the buyer that posted it, with a reason picked from a list. */
    public function reject(Request $request, int $id): RedirectResponse
    {
        $tender = Tender::where('status', Tender::PENDING)->findOrFail($id);
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(self::REASONS))]]);
        $text = self::REASONS[$data['reason']];
        $tender->update(['status' => Tender::REJECTED, 'reject_reason' => $text]);
        AdminLog::record('sent tender back', $tender->title, $text);
        $this->tellBuyer($tender, 'Your tender was not published', [$text, 'You can correct it and send it again from Posted tenders.']);

        return redirect()->route('admin.tenders.index', ['status' => Tender::PENDING])->with('success', __('The buyer is told why.'));
    }

    /** A notification under the bell of every user of the buyer and an email to its owner. */
    private function tellBuyer(Tender $tender, string $subject, array $lines): void
    {
        $company = $tender->company;
        if (! $company) {
            return;
        }
        $url = route('posted.edit', ['id' => $tender->id]);
        foreach (User::where('company_id', $company->id)->where('is_active', true)->get() as $user) {
            Notice::create(['company_id' => $company->id, 'user_id' => $user->id, 'title' => $subject, 'body' => $tender->title, 'url' => $url]);
        }
        if ($to = $company->owner()?->email) {
            try {
                Mail::to($to)->send(new BusinessMail($company, $subject, array_merge([$tender->title], $lines), 'Open the tender', $url));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}

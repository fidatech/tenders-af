<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Category;
use App\Models\Industry;
use App\Models\Province;
use App\Models\Tender;
use App\Models\TenderDocument;
use App\Support\TenderForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The tenders a buyer posts itself. A buyer writes a tender, saves it as a draft or sends
 * it for approval; an administrator publishes it or sends it back with a reason. Once it is
 * published the buyer can still extend the deadline, add documents, cancel it or name the winner.
 */
class PostedTenderController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->buyer();
        $status = isset(Tender::STATUSES[$request->query('status')]) ? $request->query('status') : null;

        return view('posted.index', [
            'company' => $company,
            'status' => $status,
            'canPost' => $company->canPostTenders(),
            'rows' => Tender::where('company_id', $company->id)->when($status, fn ($q) => $q->where('status', $status))
                ->select('tenders.*')->with('industry', 'province')->withCount('documents')
                ->addSelect(['saves' => \Illuminate\Support\Facades\DB::table('saved_tenders')->selectRaw('count(*)')->whereColumn('tender_id', 'tenders.id')])
                ->orderByDesc('id')->paginate(per_page(20))->withQueryString(),
            'counts' => Tender::where('company_id', $company->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
        ]);
    }

    public function create(): View
    {
        $this->mayPost();

        return view('posted.form', $this->lists() + ['tender' => null, 'company' => company()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->mayPost();
        $data = $this->validated($request);
        $send = $request->input('action') === 'send';

        $tender = Tender::create(TenderForm::prepare($data, null) + [
            'slug' => Tender::freeSlug($data['title']),
            'company_id' => $company->id,
            'buyer_name' => $company->name,
            'buyer_type' => $company->buyerType(),
            'source_name' => $company->name,
            'status' => $send ? Tender::PENDING : Tender::DRAFT,
            'submitted_at' => $send ? now() : null,
        ]);
        TenderForm::addDocuments($tender, $request);

        return redirect()->route('posted.edit', ['id' => $tender->id])->with('success', $send
            ? __('Your tender is sent. We check it and publish it, usually within one working day.')
            : __('Your tender is saved as a draft. Send it for approval when it is ready.'));
    }

    public function edit(int $id): View
    {
        $tender = $this->mine($id, ['documents']);

        return view('posted.form', $this->lists() + ['tender' => $tender, 'company' => company()]);
    }

    /** Before a tender is published everything can change; afterwards only through the buttons below. */
    public function update(Request $request, int $id): RedirectResponse
    {
        $tender = $this->mine($id);
        if (! $tender->isEditableByBuyer()) {
            throw new UserError(__('This tender is published. You can extend the deadline, add documents, cancel it or name the winner.'));
        }
        $send = $request->input('action') === 'send';
        if ($send) {
            $this->mayPost();
        }
        $data = TenderForm::prepare($this->validated($request), $tender);
        if ($send) {
            $data += ['status' => Tender::PENDING, 'submitted_at' => now(), 'reject_reason' => null];
        }
        $tender->update($data);
        TenderForm::addDocuments($tender, $request);

        return back()->with('success', $send ? __('Your tender is sent for approval.') : __('The tender is saved.'));
    }

    /** A published tender gets a later deadline. Companies that saved it see the new date. */
    public function extend(Request $request, int $id): RedirectResponse
    {
        $tender = $this->mine($id);
        if ($tender->status !== Tender::PUBLISHED) {
            throw new UserError(__('Only a published tender can be extended.'));
        }
        $data = $request->validate(['deadline' => ['required', 'date', 'after:now']]);
        if ($tender->deadline && $tender->deadline->gte($data['deadline'])) {
            throw new UserError(__('The new deadline must be later than the present one.'));
        }
        $tender->update(['deadline' => $data['deadline']]);

        return back()->with('success', __('The deadline is extended.'));
    }

    /** More documents for a tender that is already published (a clarification, an amendment). */
    public function addDocuments(Request $request, int $id): RedirectResponse
    {
        $tender = $this->mine($id);
        $request->validate(['documents' => ['required', 'array', 'max:10'], 'documents.*' => TenderForm::rules()['documents.*']]);
        TenderForm::addDocuments($tender, $request);

        return back()->with('success', __('The documents are added.'));
    }

    public function cancel(int $id): RedirectResponse
    {
        $tender = $this->mine($id);
        if (! in_array($tender->status, [Tender::PUBLISHED, Tender::PENDING], true)) {
            throw new UserError(__('This tender cannot be cancelled.'));
        }
        // a tender nobody saw yet simply goes back to a draft
        $tender->update(['status' => $tender->status === Tender::PENDING ? Tender::DRAFT : Tender::CANCELLED]);

        return back()->with('success', $tender->status === Tender::DRAFT ? __('The tender is taken back. It is a draft again.') : __('The tender is cancelled. It stays on the site marked as cancelled.'));
    }

    /** Name the winner. The tender stays online with its result. */
    public function award(Request $request, int $id): RedirectResponse
    {
        $tender = $this->mine($id);
        if ($tender->status !== Tender::PUBLISHED) {
            throw new UserError(__('Only a published tender can be awarded.'));
        }
        $data = $request->validate(['awarded_to' => ['required', 'string', 'max:255']]);
        $tender->update(['status' => Tender::AWARDED, 'awarded_to' => $data['awarded_to']]);

        return back()->with('success', __('The winner is published.'));
    }

    /** Only a tender that was never published can be deleted. */
    public function destroy(int $id): RedirectResponse
    {
        $tender = $this->mine($id, ['documents']);
        if (! $tender->isEditableByBuyer() || $tender->alerted_at) {
            throw new UserError(__('A tender that was published cannot be deleted. Cancel it instead.'));
        }
        foreach ($tender->documents as $document) {
            Storage::disk('local')->delete($document->path);
        }
        $tender->delete();

        return redirect()->route('posted.index')->with('success', __('The tender is deleted.'));
    }

    public function document(int $id, int $document): StreamedResponse
    {
        $file = TenderDocument::where('tender_id', $this->mine($id)->id)->findOrFail($document);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->name);
    }

    public function destroyDocument(int $id, int $document): RedirectResponse
    {
        $tender = $this->mine($id);
        if (! $tender->isEditableByBuyer()) {
            throw new UserError(__('Documents of a published tender stay, because bidders may already rely on them.'));
        }
        $file = TenderDocument::where('tender_id', $tender->id)->findOrFail($document);
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', __('The document is removed.'));
    }

    /** The signed-in company, which must be a buyer. */
    private function buyer(): \App\Models\Company
    {
        $company = company();
        abort_unless($company->is_buyer, 404);

        return $company;
    }

    /** May it post now? Free for every buyer at first; later its plan must include posting. */
    private function mayPost(): \App\Models\Company
    {
        $company = $this->buyer();
        if (! $company->canPostTenders()) {
            throw new UserError(__('Posting tenders needs a plan that includes it. Please choose one under Subscription.'));
        }

        return $company;
    }

    private function mine(int $id, array $with = []): Tender
    {
        return Tender::where('company_id', $this->buyer()->id)->with($with)->findOrFail($id);
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
        $rules = TenderForm::rules();
        $rules['deadline'] = ['required', 'date', 'after:now'];

        return $request->validate($rules, [], TenderForm::names());
    }
}

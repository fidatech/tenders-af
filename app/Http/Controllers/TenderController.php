<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Industry;
use App\Models\Province;
use App\Models\Tender;
use App\Models\TenderDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tenders as a signed-in company sees them: every tender, the ones that match its alerts,
 * and the ones it saved. The full details and the documents open on a paid plan.
 */
class TenderController extends Controller
{
    public function index(Request $request): View
    {
        return $this->list($request, 'all');
    }

    public function matching(Request $request): View
    {
        return $this->list($request, 'matching');
    }

    public function saved(Request $request): View
    {
        return $this->list($request, 'saved');
    }

    private function list(Request $request, string $mode): View
    {
        $company = company();
        $filters = $request->only('q', 'industry', 'category', 'province', 'type', 'buyer');
        $show = $request->query('show', 'open'); // open, all

        $query = Tender::visible()->filtered($filters)
            ->when($mode === 'matching', fn (Builder $q) => $q->matching($company))
            ->when($mode === 'saved', fn (Builder $q) => $q->whereIn('tenders.id', $company->savedTenders()->pluck('tenders.id')))
            ->when($show !== 'all' && $mode !== 'saved', fn (Builder $q) => $q->open());

        return view('tenders.index', [
            'mode' => $mode,
            'company' => $company,
            'filters' => $filters,
            'show' => $show,
            // tenders that close first come first; closed ones go to the end
            'rows' => $query->with('industry', 'province')
                ->orderByRaw('case when deadline is null then 1 else 0 end')
                ->orderBy($show === 'all' || $mode === 'saved' ? 'published_on' : 'deadline', $show === 'all' || $mode === 'saved' ? 'desc' : 'asc')
                ->orderByDesc('id')->paginate(per_page(20))->withQueryString(),
            'industries' => Industry::options(),
            'provinces' => Province::options(),
            'savedIds' => $company->savedTenders()->pluck('tenders.id')->all(),
            'hasAlerts' => $company->industries()->exists() || $company->categories()->exists(),
        ]);
    }

    public function show(int $id): View
    {
        $company = company();
        $tender = Tender::visible()->with('industry', 'category', 'province', 'documents')->findOrFail($id);
        $tender->increment('views');

        return view('tenders.show', [
            'tender' => $tender,
            'company' => $company,
            'full' => $company->hasFullAccess(),
            'saved' => $company->savedTenders()->where('tenders.id', $tender->id)->exists(),
            'similar' => Tender::open()->where('industry_id', $tender->industry_id)->whereKeyNot($tender->id)
                ->with('province')->orderBy('deadline')->limit(4)->get(),
        ]);
    }

    /** Save a tender for later, or take it off the list. */
    public function save(int $id): RedirectResponse
    {
        $company = company();
        $tender = Tender::visible()->findOrFail($id);

        if ($company->savedTenders()->where('tenders.id', $tender->id)->exists()) {
            $company->savedTenders()->detach($tender->id);

            return back()->with('success', __('Removed from your saved tenders.'));
        }
        $company->savedTenders()->attach($tender->id, ['created_at' => now()]);

        return back()->with('success', __('Saved. You find it under Saved tenders.'));
    }

    /** A document of a tender. Only companies on a paid plan download it. */
    public function document(int $id, int $document): StreamedResponse
    {
        $tender = Tender::visible()->findOrFail($id);
        $file = TenderDocument::where('tender_id', $tender->id)->findOrFail($document);

        if (! company()->hasFullAccess()) {
            throw new UserError(__('The documents of a tender open on a paid plan.'));
        }
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->name);
    }
}

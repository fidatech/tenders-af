<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Company;
use App\Models\CompanyProject;
use App\Models\SupplierCollection;
use App\Models\SupplierCollectionItem;
use App\Support\Modules;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Supplier Favourites & Collections. Every member of a company may use them (when the module allows
 * the company's plan). A list is shared with the team unless its maker made it private. What a company
 * writes here is never shown to the suppliers it saved, nor to any other company.
 */
class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $this->allowed($user->company);
        SupplierCollection::favouritesOf($user);

        return view('collections.index', [
            'collections' => SupplierCollection::visibleTo($user)->withCount('items')->with('user:id,name')
                ->orderByDesc('is_favourites')->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $collection = $this->visible($request, $id);
        $status = $request->query('status');
        $tag = trim((string) $request->query('tag', ''));

        $items = $collection->items()->with('supplier.province', 'supplier.industries')
            ->whereHas('supplier', fn ($q) => $q->listed())
            ->when(array_key_exists((string) $status, SupplierCollectionItem::STATUSES), fn ($q) => $q->where('status', $status))
            ->when($tag !== '', fn ($q) => $q->whereJsonContains('tags', $tag))
            ->orderByRaw("case status when 'preferred' then 0 when 'qualified' then 1 when 'shortlisted' then 2 when 'contacted' then 3 when 'researching' then 4 else 5 end")
            ->orderByDesc('updated_at')->paginate(50)->withQueryString();

        return view('collections.show', [
            'collection' => $collection,
            'items' => $items,
            'tags' => $collection->items()->pluck('tags')->flatten()->filter()->unique()->sort()->values(),
            'others' => SupplierCollection::visibleTo($request->user())->whereKeyNot($collection->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->allowed($user->company);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:300'],
            'is_private' => ['nullable', 'boolean'],
        ]);
        if (SupplierCollection::where('company_id', $user->company_id)->count() >= SupplierCollection::MAX_PER_COMPANY) {
            throw new UserError(__('A company may keep up to :n collections.', ['n' => SupplierCollection::MAX_PER_COMPANY]));
        }
        $collection = SupplierCollection::create($data + ['company_id' => $user->company_id, 'user_id' => $user->id, 'is_private' => $request->boolean('is_private')]);

        return redirect()->route('collections.show', ['id' => $collection->id])->with('success', __('Collection made.'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $collection = $this->editable($request, $id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:300'],
            'is_private' => ['nullable', 'boolean'],
        ]);
        $collection->update($data + ['is_private' => $collection->is_favourites ? false : $request->boolean('is_private')]);

        return back()->with('success', __('Collection saved.'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $collection = $this->editable($request, $id);
        if ($collection->is_favourites) {
            throw new UserError(__('The Favourites list cannot be deleted. You can empty it.'));
        }
        $collection->delete();

        return redirect()->route('collections.index')->with('success', __('Collection deleted.'));
    }

    /** Save a company into a collection (from its public page). */
    public function save(Request $request, string $slug): RedirectResponse
    {
        $user = $request->user();
        $this->allowed($user->company);
        $supplier = Company::listed()->where('slug', $slug)->firstOrFail();
        if ($supplier->id === $user->company_id) {
            throw new UserError(__('You cannot save your own company.'));
        }
        $data = $request->validate(['collection_id' => ['nullable', 'integer'], 'new_collection' => ['nullable', 'string', 'max:100']]);

        $collection = DB::transaction(function () use ($data, $user) {
            if (filled($data['new_collection'] ?? null)) {
                if (SupplierCollection::where('company_id', $user->company_id)->count() >= SupplierCollection::MAX_PER_COMPANY) {
                    throw new UserError(__('A company may keep up to :n collections.', ['n' => SupplierCollection::MAX_PER_COMPANY]));
                }

                return SupplierCollection::create(['company_id' => $user->company_id, 'user_id' => $user->id, 'name' => $data['new_collection']]);
            }

            return filled($data['collection_id'] ?? null)
                ? SupplierCollection::visibleTo($user)->findOrFail($data['collection_id'])
                : SupplierCollection::favouritesOf($user);
        });

        if ($collection->items()->count() >= SupplierCollection::MAX_ITEMS) {
            throw new UserError(__('A collection may hold up to :n companies.', ['n' => SupplierCollection::MAX_ITEMS]));
        }
        $collection->items()->firstOrCreate(['company_id' => $supplier->id], ['added_by' => $user->id]);

        return back()->with('success', __(':company is saved in :collection.', ['company' => $supplier->name, 'collection' => __($collection->name)]));
    }

    /** Change the status, note and tags of a saved company, or move it to another collection. */
    public function updateItem(Request $request, int $id, int $item): RedirectResponse
    {
        $collection = $this->visible($request, $id);
        $row = $collection->items()->findOrFail($item);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupplierCollectionItem::STATUSES))],
            'note' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'string', 'max:200'],
            'move_to' => ['nullable', 'integer'],
        ]);
        $tags = collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($t) => mb_substr(trim($t), 0, 30))->filter()->unique()->take(8)->values()->all();

        if (! empty($data['move_to'])) {
            $target = SupplierCollection::visibleTo($request->user())->findOrFail($data['move_to']);
            if ($target->items()->where('company_id', $row->company_id)->exists()) {
                $row->delete();
            } else {
                $row->update(['supplier_collection_id' => $target->id]);
            }
        }
        $row->exists && $row->update(['status' => $data['status'], 'note' => $data['note'] ?? null, 'tags' => $tags ?: null]);

        return back()->with('success', __('Saved.'));
    }

    public function removeItem(Request $request, int $id, int $item): RedirectResponse
    {
        $collection = $this->visible($request, $id);
        $collection->items()->findOrFail($item)->delete();

        return back()->with('success', __('Removed from the collection.'));
    }

    /** Up to four saved companies side by side, with facts only: no scores or ratings are made up. */
    public function compare(Request $request, int $id): View
    {
        $collection = $this->visible($request, $id);
        $ids = collect((array) $request->query('c'))->map(fn ($v) => (int) $v)->filter()->unique()->take(4);
        $items = $collection->items()->whereIn('id', $ids)->with('supplier.province', 'supplier.industries')->get()
            ->filter(fn ($item) => $item->supplier?->is_listed && $item->supplier->status !== Company::SUSPENDED)->values();
        if ($items->count() < 2) {
            throw new UserError(__('Choose two to four companies to compare.'));
        }
        $projects = Modules::enabled('portfolios')
            ? CompanyProject::visible()->whereIn('company_id', $items->pluck('company_id'))->get(['company_id', 'review_status'])->groupBy('company_id')
            : collect();

        return view('collections.compare', ['collection' => $collection, 'items' => $items, 'projects' => $projects, 'viewer' => $request->user()]);
    }

    /**
     * The collection as a CSV file: names, places, kinds of business and the company's own notes.
     * Contact details are included only where this person may see them on the company's page.
     */
    public function export(Request $request, int $id): StreamedResponse
    {
        $collection = $this->visible($request, $id);
        $user = $request->user();
        $items = $collection->items()->with('supplier.province', 'supplier.industries')->get()->filter(fn ($i) => $i->supplier?->is_listed);

        return response()->streamDownload(function () use ($items, $user) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Company', 'Page', 'Province or country', 'Kind of business', 'Sectors', 'Licence checked', 'Phone', 'Email', 'Website', 'Status', 'Tags', 'Note']);
            foreach ($items as $item) {
                $company = $item->supplier;
                $contact = $company->contactFor($user)['fields'];
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'".$v : $v, [
                    $company->name,
                    route('site.companies.show', ['slug' => $company->slug]),
                    $company->province?->name ?: $company->country,
                    implode(', ', $company->businessTypeLabels()),
                    $company->industries->pluck('name')->join(', '),
                    $company->isVerified() ? 'yes' : 'no',
                    $contact['phone'] ?? '',
                    $contact['email'] ?? '',
                    $contact['website'] ?? '',
                    SupplierCollectionItem::STATUSES[$item->status] ?? $item->status,
                    implode(', ', (array) $item->tags),
                    $item->note,
                ]));
            }
            fclose($out);
        }, str($collection->name)->slug().'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function allowed(?Company $company): void
    {
        if (! Modules::allows('collections', $company)) {
            $message = __('Saving suppliers in collections is part of the paid plans.');
            if (request()->isMethodSafe()) {
                throw new HttpResponseException(redirect()->route('billing.show')->with('warning', $message));
            }
            throw new UserError($message);
        }
    }

    private function visible(Request $request, int $id): SupplierCollection
    {
        $this->allowed($request->user()->company);

        return SupplierCollection::visibleTo($request->user())->findOrFail($id);
    }

    /** Changing or deleting a shared list: its maker or the owner of the company. */
    private function editable(Request $request, int $id): SupplierCollection
    {
        $collection = $this->visible($request, $id);
        $user = $request->user();
        abort_unless($collection->user_id === $user->id || $user->isOwner(), 403);

        return $collection;
    }
}

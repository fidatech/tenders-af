<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * One list and one form for a simple record. A screen only declares its
 * fields and columns; listing, searching, saving and deleting are shared.
 *
 * Field: ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'rules' => ['nullable'], ...]
 *   type     text, email, password, number, decimal, select, textarea, date, checkbox, file, heading
 *   options  [value => label] for select
 *   col      width on a 12-column row (default 6)
 *   meta     true: kept in the record's "meta" JSON column
 *   virtual  true: not saved on the record, only handed to afterSave()
 *   help     small grey text under the box
 *   quick    for a select: ['New client', 'clients.create', [route parameters]] puts a "+ New" link in its
 *            label, which adds a record in a pop-up without leaving the page and picks it in the list
 */
abstract class CrudController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $route;

    /** The page frame: 'layouts.app' for a business, 'layouts.admin' for the system administrators. */
    protected string $layout = 'layouts.app';

    protected string $title;

    protected string $singular;

    protected string $icon = 'file';

    /** @var array<int, string> */
    protected array $search = ['name'];

    /** @var array<int, string> */
    protected array $with = [];

    protected string $orderBy = 'name';

    protected string $orderDirection = 'asc';

    /** @return array<int, array<string, mixed>> */
    abstract protected function fields(?Model $record): array;

    /** @return array<string, callable> column label => function (Model $record) */
    abstract protected function columns(): array;

    protected function query(Request $request): Builder
    {
        return $this->model::query();
    }

    /** Extra route parameters, for screens that live under /something/{type}. */
    protected function routeParams(): array
    {
        return [];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        return $data;
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        //
    }

    /** Return a reason to refuse deleting, or null to allow it. */
    protected function deleteProblem(Model $record): ?string
    {
        return null;
    }

    /** Extra links for a row: [['label' => .., 'url' => .., 'icon' => ..]] */
    protected function rowLinks(Model $record): array
    {
        return [];
    }

    protected function canEdit(Model $record): bool
    {
        return true;
    }

    /** @var list<Model> records saved by this request */
    protected array $saved = [];

    /** One controller object can serve several requests (in tests): each starts with a clean list. */
    public function callAction($method, $parameters)
    {
        $this->saved = [];

        return parent::callAction($method, $parameters);
    }

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $rows = $this->query($request)
            ->with($this->with)
            ->when($term !== '', function (Builder $query) use ($term) {
                $query->where(function (Builder $inner) use ($term) {
                    foreach ($this->search as $column) {
                        // 'client.name' searches a column of a related record
                        if (str_contains($column, '.')) {
                            [$relation, $field] = explode('.', $column, 2);
                            $inner->orWhereHas($relation, fn (Builder $related) => $related->where($field, 'like', '%'.$term.'%'));
                        } else {
                            $inner->orWhere($column, 'like', '%'.$term.'%');
                        }
                    }
                });
            })
            ->orderBy($this->orderBy, $this->orderDirection)
            ->paginate(per_page(25))
            ->withQueryString();

        return view('crud.index', $this->shared() + [
            'rows' => $rows,
            'columns' => $this->columns(),
            'term' => $term,
            'filters' => $this->filters($request),
            'canManage' => ! str_starts_with($this->route, 'admin.')
                || \App\Support\AdminAccess::allows($request->user('admin'), $this->route.'.store', false),
            'totals' => $this->totals($request),
            'headerLinks' => $this->headerLinks($request),
            'links' => fn (Model $record) => $this->rowLinks($record),
            'editable' => fn (Model $record) => $this->canEdit($record),
        ]);
    }

    /**
     * Extra pick lists beside the search box of the list:
     * [['name' => 'currency', 'label' => 'Currency', 'options' => [value => text], 'value' => chosen], ...]
     * A filter with 'type' => 'date' is a date box instead of a pick list.
     * The screen applies them itself in query().
     */
    protected function filters(Request $request): array
    {
        return [];
    }

    public function create(Request $request): View
    {
        // ?modal=1: only the form, for the "add new" pop-up beside a pick list on another screen
        return view($request->boolean('modal') ? 'crud.modal' : 'crud.form', $this->shared() + [
            'record' => null,
            'fields' => $this->fields(null),
            'action' => route($this->route.'.store', $this->routeParams()),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $record = $this->save($request, null);
        $this->audit('added', $record);

        // added from the "add new" pop-up of another screen: that screen stays open,
        // so it is told which record was made and picks it in its list
        if ($request->boolean('_quick')) {
            return response()->json([
                'ids' => [$record->getKey()],
                'names' => array_values(array_filter([$record->getAttribute('name')])),
                'message' => __(':name saved.', ['name' => __($this->singular)]),
            ]);
        }

        return redirect()->to($this->afterStoreUrl($record, $request))->with('success', __(':name saved.', ['name' => __($this->singular)]));
    }

    /** Where to go after a new record is saved (the list, unless the screen says otherwise). */
    protected function afterStoreUrl(Model $record, Request $request): string
    {
        return route($this->route.'.index', $this->routeParams());
    }

    /** Small totals shown above the list: ['Total' => '1,200.00 USD']. */
    protected function totals(Request $request): array
    {
        return [];
    }

    /** Extra buttons in the title bar of the list: [['label' => .., 'url' => ..]] */
    protected function headerLinks(Request $request): array
    {
        return [];
    }

    public function edit(Request $request): View
    {
        $record = $this->find($request);
        abort_unless($this->canEdit($record), 403);

        return view('crud.form', $this->shared() + [
            'record' => $record,
            'fields' => $this->fields($record),
            'action' => route($this->route.'.update', $this->routeParams() + ['id' => $record->getKey()]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $record = $this->find($request);
        abort_unless($this->canEdit($record), 403);
        $this->save($request, $record);
        $this->audit('changed', $record);

        return redirect()->route($this->route.'.index', $this->routeParams())->with('success', __(':name saved.', ['name' => __($this->singular)]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $record = $this->find($request);
        abort_unless($this->canEdit($record), 403);

        if ($problem = $this->deleteProblem($record)) {
            throw new UserError($problem);
        }

        $this->audit('deleted', $record);
        try {
            DB::transaction(fn () => $record->delete());
        } catch (QueryException) {
            // something else still points at this record
            throw new UserError(__('This cannot be deleted because it is already used.'));
        }

        return redirect()->route($this->route.'.index', $this->routeParams())->with('success', __(':name deleted.', ['name' => __($this->singular)]));
    }

    /** The screens of the system administrators write what was done into the activity log. */
    protected function audit(string $action, Model $record): void
    {
        if ($this->layout === 'layouts.admin') {
            \App\Models\AdminLog::record($action.' '.mb_strtolower($this->singular), $this->auditLabel($record));
        }
    }

    /** How a record is named in the activity log. */
    protected function auditLabel(Model $record): string
    {
        return (string) ($record->getAttribute('name') ?? $record->getAttribute('title') ?? '#'.$record->getKey());
    }

    protected function find(Request $request): Model
    {
        return $this->query($request)->findOrFail($request->route('id'));
    }

    protected function save(Request $request, ?Model $record): Model
    {
        $fields = array_values(array_filter($this->fields($record), fn (array $field) => empty($field['readonly'])));

        $rules = [];
        $labels = [];
        foreach ($fields as $field) {
            $rules[$field['name']] = $field['rules'] ?? ['nullable'];
            $labels[$field['name']] = mb_strtolower(__($field['label']));
        }
        $input = $request->validate($rules, [], $labels);

        $data = [];
        $meta = $record?->meta ?? [];
        $virtual = [];
        $hasMeta = false;
        foreach ($fields as $field) {
            $name = $field['name'];
            $value = ($field['type'] ?? 'text') === 'checkbox' ? $request->boolean($name) : ($input[$name] ?? null);

            if (! empty($field['virtual'])) {
                $virtual[$name] = $value;
            } elseif (! empty($field['meta'])) {
                $meta[$name] = $value;
                $hasMeta = true;
            } elseif (($field['type'] ?? 'text') === 'password' && ($value === null || $value === '')) {
                continue; // an empty password box keeps the old password
            } else {
                $data[$name] = $value;
            }
        }
        if ($hasMeta) {
            $data['meta'] = $meta;
        }

        return DB::transaction(function () use ($data, $virtual, $record, $request) {
            $data = $this->beforeSave($data, $record, $request);
            $created = $record === null;
            $record = $record ?? new $this->model;
            $record->fill($data)->save();
            $this->afterSave($record, $virtual, $request, $created);
            $this->saved[] = $record;

            return $record;
        });
    }

    protected function shared(): array
    {
        return [
            'title' => __($this->title),
            'singular' => __($this->singular),
            'icon' => $this->icon,
            'layout' => $this->layout,
            'route' => $this->route,
            'routeParams' => $this->routeParams(),
        ];
    }
}

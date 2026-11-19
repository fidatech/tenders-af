<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Plan;
use App\Models\Company;
use App\Models\User;
use App\Support\Companies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** The companies registered on the site: open one, change its plan and end date, suspend or delete it. */
class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $rows = Company::with('plan')->withCount('users')
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('name', 'like', "%$term%")->orWhere('email', 'like', "%$term%")))
            ->when(in_array($status, [Company::TRIAL, Company::ACTIVE, Company::SUSPENDED], true), fn (Builder $q) => $q->where('status', $status))
            // "ended": suspended, or the trial or the paid time is over
            ->when($status === 'ended', fn (Builder $q) => $q->where(fn (Builder $e) => $e
                ->where('status', Company::SUSPENDED)
                ->orWhere(fn (Builder $a) => $a->where('status', Company::ACTIVE)->where('subscription_ends_at', '<', now()->toDateString()))
                ->orWhere(fn (Builder $t) => $t->where('status', Company::TRIAL)->where('trial_ends_at', '<', now()->toDateString()))))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.companies.index', ['rows' => $rows, 'term' => $term, 'status' => $status]);
    }

    public function create(): View
    {
        return view('admin.companies.form', ['company' => null, 'plans' => Plan::orderBy('sort')->get(), 'timezones' => timezone_identifiers_list()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['owner_email' => strtolower(trim((string) $request->input('owner_email'))), 'currency' => strtoupper(trim((string) $request->input('currency')))]);
        $data = $request->validate($this->rules() + [
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', Password::min(8)],
        ]);

        $company = Companies::create($data);
        // the currency follows the kind of company unless the administrator typed another one
        $company->update(array_filter(['currency' => $data['currency'], 'notes' => $data['notes'] ?? null, 'trial_ends_at' => $data['status'] === Company::TRIAL ? ($data['trial_ends_at'] ?? null) : null]));

        AdminLog::record('opened company', $company->name, 'Owner: '.$data['owner_email']);

        return redirect()->route('admin.companies.edit', ['id' => $company->id])->with('success', __('The company is opened. Its owner can sign in now.'));
    }

    public function edit(int $id): View
    {
        $company = Company::findOrFail($id);

        return view('admin.companies.form', [
            'company' => $company,
            'plans' => Plan::orderBy('sort')->get(),
            'timezones' => timezone_identifiers_list(),
            'users' => User::where('company_id', $company->id)->orderBy('id')->get(),
            'subscriptions' => $company->subscriptions()->with('plan', 'account')->orderByDesc('starts_on')->orderByDesc('id')->get(),
                        'usage' => [
                'Users' => User::where('company_id', $company->id)->count(),
                'Sectors' => $company->industries()->count(),
                'Saved tenders' => $company->savedTenders()->count(),
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $request->merge(['currency' => strtoupper(trim((string) $request->input('currency')))]);
        $data = $request->validate($this->rules() + ['email' => ['nullable', 'email', 'max:255']]);

        if ($data['status'] !== Company::TRIAL) {
            $data['trial_ends_at'] = $company->trial_ends_at;
        }
        $before = $company->only('status', 'plan_id', 'trial_ends_at', 'subscription_ends_at');
        if ($data['name'] !== $company->name) {
            $data['slug'] = Company::freeSlug($data['name'], $company->id);
        }
        $company->update($data);

        // the log says what changed about the access of the company, in words
        $changes = [];
        if ($before['status'] !== $company->status) {
            $changes[] = 'status: '.$before['status'].' → '.$company->status;
        }
        foreach (['trial_ends_at' => 'trial ends', 'subscription_ends_at' => 'paid until'] as $key => $label) {
            if ($before[$key]?->toDateString() !== $company->{$key}?->toDateString()) {
                $changes[] = $label.': '.($before[$key]?->toDateString() ?? 'none').' → '.($company->{$key}?->toDateString() ?? 'none');
            }
        }
        if ((int) $before['plan_id'] !== (int) $company->plan_id) {
            $changes[] = 'plan changed';
        }
        AdminLog::record($before['status'] !== $company->status && $company->status === Company::SUSPENDED ? 'suspended company' : 'changed company', $company->name, implode('; ', $changes) ?: null);

        return back()->with('success', __('The company is saved.'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        // typing the name protects against a wrong click: everything of the company goes with it
        $request->validate(['confirm' => ['required', Rule::in([$company->name])]], ['confirm.in' => __('Type the name of the company exactly to delete it.')]);
        AdminLog::record('deleted company', $company->name, 'Email: '.$company->email);
        $company->delete();

        return redirect()->route('admin.companies.index')->with('success', __('The company and everything it recorded is deleted.'));
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(array_keys(Company::KINDS))],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'alpha', 'size:3'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'status' => ['required', Rule::in([Company::TRIAL, Company::ACTIVE, Company::SUSPENDED])],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

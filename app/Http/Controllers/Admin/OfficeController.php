<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\Company;
use App\Models\OfficeEntry;
use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Models\PlatformExpense;
use App\Models\Subscription;
use App\Support\Lifecycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class OfficeController extends Controller
{
    private const COLLECTION_ACCOUNTS = ['cash', 'mobile', 'online'];

    public function index(Request $request): View
    {
        return $this->register($request, false);
    }

    public function approvals(Request $request): View
    {
        return $this->register($request, true);
    }

    private function register(Request $request, bool $review): View
    {
        $employee = $request->user('admin');
        $query = OfficeEntry::query()->when(! $employee->isSuper(), fn (Builder $query) => $query->where('employee_id', $employee->id));
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'kind' => ['nullable', Rule::in(array_keys(OfficeEntry::KINDS))],
            'status' => ['nullable', Rule::in(array_keys(OfficeEntry::STATUSES))],
            'employee' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
        ]);
        $term = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? ($review && ! $request->has('status') ? 'pending' : null);
        $employeeId = $employee->isSuper() ? ($filters['employee'] ?? null) : null;
        $scoped = (clone $query)->when($employeeId, fn (Builder $query) => $query->where('employee_id', $employeeId));
        $balances = (clone $scoped)->select('employee_id', 'platform_account_id', 'currency')->custody()
            ->groupBy('employee_id', 'platform_account_id', 'currency')->with('employee', 'account')->get();
        $entries = (clone $scoped)->with('employee', 'reviewer', 'account', 'company', 'plan', 'subscription')
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('reference', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%')
                ->orWhereHas('company', fn (Builder $company) => $company->where('name', 'like', '%'.$term.'%'))))
            ->when($filters['kind'] ?? null, fn (Builder $query, $kind) => $query->where('kind', $kind))
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->where('date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->where('date', '<=', $to))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->latest('id')->paginate(25)->withQueryString();
        $employees = $employee->isSuper() ? Admin::orderBy('name')->pluck('name', 'id') : collect();

        return view('admin.office.index', compact('employee', 'employees', 'employeeId', 'balances', 'entries', 'status', 'review', 'term'));
    }

    public function create(Request $request): View
    {
        $kind = $request->query('kind', 'subscription');
        abort_unless(is_string($kind) && isset(OfficeEntry::KINDS[$kind]), 404);
        $accounts = PlatformAccount::when($kind !== 'handover', fn ($query) => $query->where('is_active', true))
            ->when(in_array($kind, ['subscription', 'handover'], true), fn ($query) => $query->whereIn('type', self::COLLECTION_ACCOUNTS))
            ->orderBy('name')->get();
        $companies = $kind === 'subscription' ? Company::orderBy('name')->get(['id', 'name', 'currency']) : collect();
        $plans = $kind === 'subscription' ? Plan::where('is_active', true)->where('price', '>', 0)->orderBy('sort')->get() : collect();

        return view('admin.office.create', compact('kind', 'accounts', 'companies', 'plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $request->user('admin');
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(OfficeEntry::KINDS))],
            'platform_account_id' => ['required', 'integer', 'exists:platform_accounts,id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->toDateString()],
            'period_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:date'],
            'company_id' => ['required_if:kind,subscription', 'nullable', 'integer', 'exists:companies,id'],
            'plan_id' => ['required_if:kind,subscription', 'nullable', 'integer', 'exists:plans,id'],
            'category' => ['required_if:kind,expense', 'nullable', Rule::in(array_keys(PlatformExpense::CATEGORIES))],
            'employee_funds' => ['nullable', 'boolean'],
            'reference' => ['nullable', 'string', 'max:150'],
            'description' => ['required_if:kind,income,expense', 'nullable', 'string', 'max:250'],
            'proof' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);
        $kind = $data['kind'];
        $employeeFunds = in_array($kind, ['subscription', 'handover'], true) || $request->boolean('employee_funds');
        $path = $request->file('proof')?->store('office-proofs', 'local');
        if ($path === false) {
            throw new UserError(__('The proof could not be saved. Please try again.'));
        }

        try {
            DB::transaction(function () use ($employee, $data, $kind, $employeeFunds, $path) {
                self::lockRegister();
                $account = PlatformAccount::findOrFail($data['platform_account_id']);
                if ((! $account->is_active && $kind !== 'handover') || ($employeeFunds && ! in_array($account->type, self::COLLECTION_ACCOUNTS, true))) {
                    throw new UserError(__('Choose a valid account for this entry.'));
                }
                if ($kind === 'subscription' && $account->type !== 'cash' && empty($data['reference'])) {
                    throw new UserError(__('Enter the HesabPay transaction reference.'));
                }

                $subscription = null;
                if ($kind === 'subscription') {
                    $company = Company::findOrFail($data['company_id']);
                    $plan = Plan::where('is_active', true)->findOrFail($data['plan_id']);
                    $subscription = $company->subscriptions()->waiting()->first();
                    if ($subscription && (int) $subscription->plan_id !== $plan->id) {
                        throw new UserError(__('This company already has a pending request for another plan.'));
                    }
                    if ($company->currency !== $account->currency || $plan->currency !== $account->currency
                        || $plan->isFree() || self::cents($subscription?->amount ?? $plan->price) !== self::cents($data['amount'])) {
                        throw new UserError(__('The account currency and amount must match the subscription.'));
                    }
                    if (OfficeEntry::where('company_id', $company->id)->where('kind', 'subscription')->where('status', 'pending')->exists()) {
                        throw new UserError(__('This company already has a collection awaiting review.'));
                    }
                    $this->checkReference($account, $data['reference'] ?? null, $subscription?->id);
                }

                if ($kind === 'handover' && self::cents($data['amount']) > $this->available($employee->id, $account->id, true)) {
                    throw new UserError(__('The handover exceeds the available approved funds in this account.'));
                }

                $entry = OfficeEntry::create([
                    'employee_id' => $employee->id, 'platform_account_id' => $account->id,
                    'kind' => $kind, 'currency' => $account->currency, 'amount' => $data['amount'], 'date' => $data['date'],
                    'employee_funds' => $employeeFunds, 'period_from' => $kind === 'handover' ? ($data['period_from'] ?? null) : null,
                    'company_id' => $kind === 'subscription' ? $data['company_id'] : null,
                    'plan_id' => $kind === 'subscription' ? $data['plan_id'] : null, 'subscription_id' => $subscription?->id,
                    'category' => $kind === 'expense' ? $data['category'] : null, 'proof_path' => $path,
                    'reference' => $data['reference'] ?? null, 'description' => $data['description'] ?? null,
                ]);
                AdminLog::record('submitted office entry', '#'.$entry->id, $kind.' · '.$entry->amount.' '.$entry->currency);
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('admin.office.index')->with('success', __('Entry submitted for owner review.'));
    }

    public function review(Request $request, int $id): RedirectResponse
    {
        $decision = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'required_if:decision,rejected', 'string', 'max:250'],
        ]);
        $subscription = DB::transaction(function () use ($request, $id, $decision) {
            self::lockRegister();
            $entry = OfficeEntry::with(['account', 'plan'])->findOrFail($id);
            if ($entry->status !== 'pending') {
                throw new UserError(__('This entry has already been reviewed.'));
            }

            $subscription = null;
            if ($decision['decision'] === 'approved') {
                if ($entry->employee_funds && in_array($entry->kind, ['expense', 'handover'], true)
                    && self::cents($entry->amount) > $this->available($entry->employee_id, $entry->platform_account_id, false)) {
                    throw new UserError(__('There are insufficient approved employee funds in this account.'));
                }
                if ($entry->kind === 'subscription') {
                    $subscription = $this->approveSubscription($entry);
                    $entry->subscription_id = $subscription->id;
                }
                if ($entry->kind === 'expense') {
                    $entry->expense_id = PlatformExpense::create([
                        'platform_account_id' => $entry->platform_account_id, 'category' => $entry->category,
                        'amount' => $entry->amount, 'currency' => $entry->currency, 'date' => $entry->date,
                        'description' => $entry->description,
                    ])->id;
                }
            }

            $entry->fill([
                'status' => $decision['decision'], 'reviewed_by' => $request->user('admin')->id,
                'reviewed_at' => now(), 'review_note' => $decision['review_note'] ?? null,
            ])->save();
            AdminLog::record($decision['decision'].' office entry', '#'.$entry->id, $entry->kind.' · '.$entry->amount.' '.$entry->currency);

            return $subscription;
        });

        if ($subscription) {
            try {
                Lifecycle::paymentConfirmed($subscription);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return back()->with('success', __('Review recorded.'));
    }

    public function proof(Request $request, int $id): StreamedResponse
    {
        $employee = $request->user('admin');
        $entry = OfficeEntry::when(! $employee->isSuper(), fn ($query) => $query->where('employee_id', $employee->id))->findOrFail($id);
        abort_unless($entry->proof_path && Storage::disk('local')->exists($entry->proof_path), 404);

        return Storage::disk('local')->download($entry->proof_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public static function lockRegister(): void
    {
        DB::table('settings')->where('key', 'office_register_lock')->lockForUpdate()->first();
    }

    private function approveSubscription(OfficeEntry $entry): Subscription
    {
        $company = Company::lockForUpdate()->find($entry->company_id);
        $plan = $entry->plan;
        if (! $company || ! $plan || $company->currency !== $entry->currency || $plan->currency !== $entry->currency) {
            throw new UserError(__('The company or subscription plan has changed. Review the collection before proceeding.'));
        }
        $subscription = $entry->subscription_id ? Subscription::lockForUpdate()->find($entry->subscription_id) : null;
        if ($entry->subscription_id ? (! $subscription?->isWaiting() || (int) $subscription->plan_id !== $plan->id
            || self::cents($subscription->amount) !== self::cents($entry->amount)) : $company->subscriptions()->waiting()->exists()) {
            throw new UserError(__('The company payment request changed after submission. Reject this entry and review the payment again.'));
        }
        if ($plan->max_users && $company->users()->count() > $plan->max_users) {
            throw new UserError(__('The company has more users than this subscription plan allows.'));
        }
        $this->checkReference($entry->account, $entry->reference, $subscription?->id, $entry->id);

        if (! $subscription) {
            [$from, $until] = Subscription::billingPeriod($company, $plan);
            $subscription = new Subscription([
                'company_id' => $company->id, 'plan_id' => $plan->id, 'amount' => $entry->amount,
                'currency' => $entry->currency, 'starts_on' => $from, 'ends_on' => $until,
            ]);
        }
        $subscription->fill([
            'paid_on' => $entry->date, 'platform_account_id' => $entry->platform_account_id,
            'reference' => $entry->reference ?? $subscription->reference,
        ])->save();
        $subscription->assignReceiptNumber();
        $subscription->apply();

        return $subscription;
    }

    private function checkReference(PlatformAccount $account, ?string $reference, ?int $subscriptionId, ?int $entryId = null): void
    {
        if ($account->type === 'cash' || $reference === null) {
            return;
        }
        $used = OfficeEntry::where('kind', 'subscription')->whereIn('status', ['pending', 'approved'])->where('reference', $reference)
            ->when($entryId, fn ($query) => $query->where('id', '!=', $entryId))->exists()
            || Subscription::where('platform_account_id', $account->id)->whereNotNull('paid_on')->where('reference', $reference)
                ->when($subscriptionId, fn ($query) => $query->where('id', '!=', $subscriptionId))->exists();
        if ($used) {
            throw new UserError(__('This transaction reference has already been recorded.'));
        }
    }

    private function available(int $employeeId, int $accountId, bool $reservePendingHandovers): int
    {
        $entries = OfficeEntry::where('employee_id', $employeeId)->where('platform_account_id', $accountId);
        $held = self::cents((clone $entries)->custody()->first()?->held ?? 0);
        $reserved = $reservePendingHandovers ? self::cents($entries->where('status', 'pending')->where('kind', 'handover')->sum('amount')) : 0;

        return $held - $reserved;
    }

    private static function cents(string|int|float $value): int
    {
        return (int) round((float) $value * 100);
    }
}

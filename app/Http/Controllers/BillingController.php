<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Mail\PaymentRequested;
use App\Models\Admin;
use App\Models\User;
use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Models\Subscription;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The Subscription page of a company: how long it may still use the system, what the plan costs,
 * how to pay, and the "I have paid" form. The money is checked by a system administrator, who
 * confirms the request under /admin > Subscriptions; only then the company becomes active.
 */
class BillingController extends Controller
{
    public function show(Request $request): View
    {
        $company = company();
        $plans = $this->plans($company);
        $plan = $this->chosen($company, $plans, $request->query('plan'));
        $users = User::where('company_id', $company->id)->count();

        return view('billing.show', [
            'company' => $company,
            'plans' => $plans,
            'plan' => $plan,
            'current' => $company->plan,
            // a plan that allows fewer users than the company already has cannot be chosen
            'tooSmall' => $plans->mapWithKeys(fn (Plan $p) => [$p->id => $this->tooSmall($p, $users)])->all(),
            'left' => $company->daysLeft(),
            // a paid period cannot be changed or paid back: choosing opens only near its end (or after it)
            'open' => $this->open($company),
            'opensOn' => $this->opensOn($company),
            // plans higher than the running one: these can be taken at any time, and start today
            'upgrades' => $plans->mapWithKeys(fn (Plan $p) => [$p->id => $this->isUpgrade($company, $p)])->all(),
            'period' => $plan ? $this->period($company, $plan) : null,
            // the free plan can be taken, without paying, by a company on a trial or whose time has ended
            'canFree' => $this->canFree($company),
            // plans are paid by the month or by the year: the page shows one of the two at a time
            'cycles' => $cycles = $this->cycles($plans),
            'cycle' => $plan && in_array($plan->interval, $cycles, true) ? $plan->interval : ($cycles[0] ?? null),
            'saving' => $this->saving($plans),
            // the ways to pay in the currency of the company (bank accounts, HesabPay, cash ...); all of them when none is in that currency
            'accounts' => $this->accounts($company),
            'pending' => $pending = $company->subscriptions()->waiting()->with('plan')->orderByDesc('id')->first(),
            // the last request, when it was rejected: shown with its reason above the form
            'rejected' => $pending ? null : $this->lastRejected($company),
            'history' => $company->subscriptions()->with('plan')->whereNotNull('paid_on')->orderByDesc('starts_on')->limit(10)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = company();
        $plans = $this->plans($company)->where('is_active', true);
        if ($plans->isEmpty()) {
            throw new UserError(__('There is no plan to subscribe to yet. Please contact support.'));
        }
        if ($company->subscriptions()->waiting()->exists()) {
            throw new UserError(__('Your payment is already waiting to be confirmed.'));
        }
        $picked = $plans->firstWhere('id', (int) $request->input('plan_id'));
        if ($picked?->isFree()) {
            return $this->moveToFree($company, $picked);
        }
        if (! $this->open($company) && ! ($picked && $this->isUpgrade($company, $picked))) {
            throw new UserError(__('Your current plan is paid until :date. You can renew it or choose another plan from :from.', ['date' => fmt_date($company->endsOn()), 'from' => fmt_date($this->opensOn($company))]));
        }

        $data = $request->validate([
            'plan_id' => ['required', Rule::in($plans->pluck('id')->all())],
            'sent_on' => ['required', 'date', 'before_or_equal:'.Carbon::parse(today_iso())->addDay()->toDateString()],
            'platform_account_id' => ['nullable', Rule::exists('platform_accounts', 'id')->where('is_active', true)],
            'reference' => ['nullable', 'string', 'max:255'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], ['receipt.required' => __('Please add the screenshot of your payment.'), 'plan_id.required' => __('Choose a plan first.')]);

        $plan = $plans->firstWhere('id', (int) $data['plan_id']);
        if ($problem = $this->tooSmall($plan, User::where('company_id', $company->id)->count())) {
            throw new UserError($problem);
        }

        [$from, $until] = $this->period($company, $plan);

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'platform_account_id' => $data['platform_account_id'] ?? null,
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'starts_on' => $from,
            'ends_on' => $until,
            'requested' => true,
            'sent_on' => $data['sent_on'],
            'reference' => $data['reference'] ?? null,
            'receipt_path' => $request->file('receipt')?->store('receipts/'.$company->id, 'local'),
        ]);

        $this->tellAdministrators($subscription);

        return redirect()->route('billing.show')->with('success', __('Thank you. We will check your payment and switch your subscription on.'));
    }

    /**
     * May the company choose a plan and pay now? Yes on a trial and after the end. While a paid
     * period runs: only in its last days (Settings: "renewal opens"), and what is bought then
     * starts when the current period ends. A company with no end date has nothing to pay.
     */
    private function open(Company $company): bool
    {
        if ($company->status === Company::SUSPENDED) {
            return false;
        }
        if ($company->status === Company::TRIAL || ! $company->isUsable() || $company->onFreePlan()) {
            return true;
        }
        $left = $company->daysLeft();

        return $left !== null && $left <= (int) platform('renew_open_days');
    }

    /**
     * Is this plan a step up from the paid period that is running? Then it can be taken at any
     * time: it is paid in full and starts today, and the days left on the old plan are not paid back.
     */
    private function isUpgrade(Company $company, Plan $plan): bool
    {
        $current = $company->plan;

        return $company->status === Company::ACTIVE && $company->isUsable() && $company->endsOn() !== null
            && $current && $plan->is_active && $plan->id !== $current->id
            && strtoupper($plan->currency) === strtoupper($current->currency)
            // by what a month costs, so a yearly plan is not "higher" than a bigger plan paid monthly
            && round($plan->monthlyRate(), 4) > round($current->monthlyRate(), 4);
    }

    /** May the company move to the free plan now? Only from a trial or after its time ended: a paid period is never cut short. */
    private function canFree(Company $company): bool
    {
        return $company->status !== Company::SUSPENDED && ! $company->onFreePlan()
            && ($company->status === Company::TRIAL || ! $company->isUsable());
    }

    /** Nothing is paid for the free plan, so nothing is checked: the company is on it at once. */
    private function moveToFree(Company $company, Plan $plan): RedirectResponse
    {
        if ($company->onFreePlan()) {
            throw new UserError(__('You are already on this plan.'));
        }
        if (! $this->canFree($company)) {
            throw new UserError(__('Your current plan is paid until :date. You can move to the free plan after that.', ['date' => fmt_date($company->endsOn())]));
        }
        if ($problem = $this->tooSmall($plan, User::where('company_id', $company->id)->count())) {
            throw new UserError($problem);
        }
        $company->update(['plan_id' => $plan->id, 'status' => Company::ACTIVE, 'subscription_ends_at' => null]);

        return redirect()->route('billing.show')->with('success', __('You are on the :plan plan now.', ['plan' => $plan->name]));
    }

    /** "monthly" and "yearly", when the paid plans on offer come in both; otherwise the page needs no switch. */
    private function cycles(Collection $plans): array
    {
        $offered = $plans->filter(fn (Plan $p) => $p->is_active && ! $p->isFree())->pluck('interval')->unique();

        return $offered->contains('monthly') && $offered->contains('yearly') ? ['monthly', 'yearly'] : [];
    }

    /** The most a company saves, in percent, by paying a plan for a year instead of twelve months. */
    private function saving(Collection $plans): int
    {
        $offered = $plans->filter(fn (Plan $p) => $p->is_active && ! $p->isFree());

        return (int) $offered->where('interval', 'yearly')->map(function (Plan $year) use ($offered) {
            $month = $offered->first(fn (Plan $p) => $p->interval === 'monthly' && $p->name === $year->name && $p->currency === $year->currency);

            return $month ? (int) round(100 * (1 - (float) $year->price / (12 * (float) $month->price))) : 0;
        })->max();
    }

    /** The first day a company with a running paid period may renew or change its plan. */
    private function opensOn(Company $company): ?Carbon
    {
        $end = $company->status === Company::ACTIVE ? $company->subscription_ends_at : null;

        return $end ? $end->copy()->subDays((int) platform('renew_open_days')) : null;
    }

    /** The plans on offer, in their order; the plan of the company is added when it is no longer offered. */
    private function plans(Company $company): Collection
    {
        // Afghan companies see the AFN prices; NGOs and international clients the USD prices
        $plans = Plan::offeredIn($company->currency ?: 'USD')->get();
        if ($company->plan && ! $plans->contains('id', $company->plan_id)) {
            $plans->push($company->plan);
        }

        return $plans;
    }

    /** The plan the page is about: the one picked in the address, else the plan of the company, else the first on offer. */
    private function chosen(Company $company, Collection $plans, mixed $picked): ?Plan
    {
        // the free plan is never paid for, so it is never the plan to pay
        $offered = $plans->filter(fn (Plan $p) => $p->is_active && ! $p->isFree());

        return $offered->firstWhere('id', (int) $picked)
            ?? $offered->firstWhere('id', $company->plan_id)
            ?? $offered->first();
    }

    /** Why a company cannot take this plan, or null when it fits. */
    private function tooSmall(Plan $plan, int $users): ?string
    {
        if ($plan->max_users && $users > $plan->max_users) {
            return __('You have :have users and this plan allows :max.', ['have' => number_format($users), 'max' => number_format($plan->max_users)]);
        }

        return null;
    }

    /** The newest "I have paid" request of the company, if it was rejected and nothing was paid after it. */
    private function lastRejected(Company $company): ?Subscription
    {
        $last = $company->subscriptions()->where('requested', true)->orderByDesc('id')->first();
        if (! $last?->isRejected()) {
            return null;
        }

        return $company->subscriptions()->whereNotNull('paid_on')->where('id', '>', $last->id)->exists() ? null : $last;
    }

    /** The accounts a company can pay into: those in its own currency first; every account in use when there is none. */
    private function accounts(Company $company): Collection
    {
        $all = PlatformAccount::where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $own = $all->where('currency', strtoupper((string) $company->currency))->values();

        return $own->isNotEmpty() ? $own : $all;
    }

    /** The numbered receipt of one of the company's own confirmed payments. */
    public function receipt(int $id): View
    {
        $subscription = company()->subscriptions()->with('company', 'plan', 'account')->whereNotNull('receipt_no')->findOrFail($id);

        return view('receipts.show', ['subscription' => $subscription]);
    }

    /** The QR code of a payment account, for the Subscription page. */
    public function qr(int $id): StreamedResponse
    {
        $account = PlatformAccount::where('is_active', true)->findOrFail($id);
        abort_unless($account->qr_path && Storage::disk('local')->exists($account->qr_path), 404);

        return Storage::disk('local')->response($account->qr_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** An email to the platform owner, with the screenshot. A mail problem never loses the request itself. */
    private function tellAdministrators(Subscription $subscription): void
    {
        $to = array_values(array_filter(array_map('trim', explode(',', (string) platform('billing_email')))))
            ?: Admin::where('is_active', true)->pluck('email')->all();
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new PaymentRequested($subscription));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The period a new payment covers: from today, or from the day after the paid time that is
     * still running, for one month or one year as the plan says (no end for a lifetime plan).
     *
     * @return array{0: Carbon, 1: ?Carbon}
     */
    private function period(Company $company, Plan $plan): array
    {
        $today = Carbon::parse(today_iso());
        $paidUntil = $company->status === Company::ACTIVE ? $company->subscription_ends_at : null;
        // an upgrade starts today; a renewal or another plan starts when the paid time ends
        $from = $paidUntil && $paidUntil->gte($today) && ! $this->isUpgrade($company, $plan) ? $paidUntil->copy()->addDay() : $today;

        $until = match ($plan->interval) {
            'monthly' => $from->copy()->addMonthNoOverflow(),
            'lifetime' => null,
            default => $from->copy()->addYearNoOverflow(),
        };

        return [$from, $until];
    }
}

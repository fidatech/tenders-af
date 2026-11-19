<?php

namespace App\Support;

use App\Mail\BusinessMail;
use App\Models\Notice;
use App\Models\Subscription;
use App\Models\Company;
use App\Models\CompanyMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails from the platform to its companies about their own account: the trial is ending,
 * the subscription is ending, it has ended, a payment is confirmed. Each kind goes at most
 * once a day to a company, and a mail problem never stops the work that caused it.
 */
class Lifecycle
{
    /** Called by the scheduler for one company. Returns the kind of email that went out, or null. */
    public static function check(Company $company): ?string
    {
        if ($company->status === Company::SUSPENDED) {
            return null;
        }
        $left = $company->daysLeft();
        if ($left === null) {
            return null; // no end date
        }
        $trial = $company->status === Company::TRIAL;
        $warn = Platform::days($trial ? 'trial_warn_days' : 'renew_warn_days');

        $kind = match (true) {
            $left === -1 => $trial ? 'trial_ended' : 'subscription_ended',
            $left === 0, in_array($left, $warn, true) => $trial ? 'trial_ending' : 'renewal_due',
            default => null,
        };
        // a company whose payment is being checked is not chased
        if (! $kind || $company->subscriptions()->waiting()->exists()) {
            return null;
        }

        $date = fmt_date($company->endsOn());
        $when = $left === 0 ? 'today' : ($left === 1 ? 'tomorrow' : 'in '.$left.' days');
        [$subject, $lines] = match ($kind) {
            'trial_ending' => ["Your free trial ends $when", [
                "Your free trial of ".config('app.name')." for {$company->name} ends $when, on $date.",
                'To keep the full tenders and your alerts without a break, subscribe before then. Your company profile stays as it is.',
            ]],
            'trial_ended' => ['Your free trial has ended', [
                "The free trial for {$company->name} ended on $date.",
                'Your company profile stays in the directory. Subscribe to open the full tenders and the alerts again.',
            ]],
            'renewal_due' => ["Your subscription ends $when", [
                "The subscription of {$company->name} ends $when, on $date.",
                'Renew it before then to keep the full tenders and your alerts. The new period starts when the current one ends, so you lose no days by paying early.',
            ]],
            'subscription_ended' => ['Your subscription has ended', [
                "The subscription of {$company->name} ended on $date.",
                'Your company profile stays in the directory. Renew to open the full tenders and the alerts again.',
            ]],
        };

        return self::send($company, $kind, $subject, $lines, 'Open the Subscription page', route('billing.show')) ? $kind : null;
    }

    /** A payment was confirmed: thank the company and point it to its receipt. */
    public static function paymentConfirmed(Subscription $subscription): void
    {
        $company = $subscription->company;
        if (! $company) {
            return;
        }
        $until = $subscription->ends_on ? 'until '.fmt_date($subscription->ends_on) : 'with no end date';

        self::send($company, 'payment_confirmed', 'Your payment is confirmed', [
            'We received your payment of '.money($subscription->amount, $subscription->currency).'. Thank you.',
            "The subscription of {$company->name} is active $until.",
            'Receipt number: '.$subscription->receiptNumber(),
        ], 'See the receipt', route('billing.receipt', ['id' => $subscription->id]));
    }

    /** One email and one notification under the bell. False when this kind already went today. */
    private static function send(Company $company, string $kind, string $subject, array $lines, string $button, string $url): bool
    {
        $today = now($company->timezone ?: 'UTC')->toDateString();
        $log = CompanyMail::firstOrCreate(['company_id' => $company->id, 'kind' => $kind, 'on_date' => $today]);
        if (! $log->wasRecentlyCreated) {
            return false;
        }

        $users = User::where('company_id', $company->id)->where('is_active', true)->get();
        foreach ($users as $user) {
            Notice::create(['company_id' => $company->id, 'user_id' => $user->id, 'title' => $subject, 'body' => $lines[0], 'url' => $url]);
        }

        $to = $users->where('role', User::OWNER)->pluck('email')->merge([$company->email])->filter()->unique()->values()->all();
        if ($to) {
            try {
                Mail::to($to)->send(new BusinessMail($company, $subject, $lines, $button, $url));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return true;
    }
}

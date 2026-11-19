<?php

namespace App\Models;

use App\Casts\Day;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period a business pays for. Written by a system administrator, or sent by the business itself
 * from its Subscription page ("I have paid"). While paid_on is empty the money is not confirmed.
 * Once it is confirmed, the business follows it: plan, active status and end date.
 */
class Subscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => Day::class, 'ends_on' => Day::class, 'paid_on' => Day::class, 'sent_on' => Day::class, 'requested' => 'boolean', 'rejected_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class, 'platform_account_id');
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    /** Give a confirmed payment its receipt number: the next one in line. */
    public function assignReceiptNumber(): void
    {
        if ($this->isPaid() && ! $this->receipt_no) {
            $this->update(['receipt_no' => ((int) static::max('receipt_no')) + 1]);
        }
    }

    /** "DB-00012", or null while the payment is not confirmed. */
    public function receiptNumber(): ?string
    {
        return $this->receipt_no ? \App\Support\Platform::get('receipt_prefix').str_pad((string) $this->receipt_no, 5, '0', STR_PAD_LEFT) : null;
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null && ! $this->isPaid();
    }

    /** Sent by the business and neither confirmed nor rejected yet. */
    public function isWaiting(): bool
    {
        return $this->requested && ! $this->isPaid() && $this->rejected_at === null;
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('requested', true)->whereNull('paid_on')->whereNull('rejected_at');
    }

    /** The reason of the rejection as the business reads it. */
    public function rejectText(): string
    {
        return __(config('tenders.reject_reasons')[$this->reject_reason]['text'] ?? 'The payment could not be accepted.');
    }

    /** What the business should do after this rejection. */
    public function rejectAdvice(): string
    {
        return __(config('tenders.reject_reasons')[$this->reject_reason]['advice'] ?? 'Please pay and send the screenshot again.');
    }

    /** The short name of the reason, for the lists of the administrators. */
    public function rejectLabel(): string
    {
        return __(config('tenders.reject_reasons')[$this->reject_reason]['label'] ?? 'Rejected');
    }

    /** After this payment is confirmed, the business follows its paid periods. */
    public function apply(): void
    {
        if ($this->isPaid() && $this->company) {
            static::syncCompany($this->company);
        }
    }

    /**
     * Put a business in step with what it paid for: it is active until the end of its last
     * paid period, and its plan is the plan of the period that is running today. A period
     * bought for later (a renewal, or another plan chosen before the current one ended)
     * only changes the plan on the day it starts: nothing is paid back and no days are lost.
     */
    public static function syncCompany(Company $company): void
    {
        $paid = $company->subscriptions()->whereNotNull('paid_on')->get();
        if ($paid->isEmpty()) {
            return;
        }
        // a business that moved to the free plan after its paid time ended stays there until it pays again
        $today = now($company->timezone ?: 'UTC')->toDateString();
        if ($company->onFreePlan() && ! $paid->contains(fn (self $s) => $s->ends_on === null || $s->ends_on->toDateString() >= $today)) {
            return;
        }
        $forever = $paid->contains(fn (self $s) => $s->ends_on === null);
        $running = static::runningFor($company, $paid) ?? $paid->sortByDesc(fn (self $s) => $s->ends_on?->toDateString() ?? '9999-12-31')->first();

        $company->update([
            'plan_id' => $running->plan_id,
            'subscription_ends_at' => $forever ? null : $paid->max(fn (self $s) => $s->ends_on->toDateString()),
            'status' => $company->status === Company::SUSPENDED ? Company::SUSPENDED : Company::ACTIVE,
        ]);
    }

    /** The paid period of a business that covers today (the newest one, if several do). */
    public static function runningFor(Company $company, ?\Illuminate\Support\Collection $paid = null): ?self
    {
        $today = now($company->timezone ?: 'UTC')->toDateString();
        $paid ??= $company->subscriptions()->whereNotNull('paid_on')->get();

        return $paid
            ->filter(fn (self $s) => $s->starts_on->toDateString() <= $today && ($s->ends_on === null || $s->ends_on->toDateString() >= $today))
            ->sortByDesc(fn (self $s) => $s->starts_on->toDateString().'-'.str_pad((string) $s->id, 10, '0', STR_PAD_LEFT))
            ->first();
    }
}

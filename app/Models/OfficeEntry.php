<?php

namespace App\Models;

use App\Casts\Day;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeEntry extends Model
{
    public const KINDS = [
        'subscription' => 'Subscription collection',
        'income' => 'Other income',
        'expense' => 'Expense',
        'handover' => 'Handover',
    ];

    /** The icon of each kind in the register and on the form (listed in resources/assets/safelist.txt). */
    public const ICONS = [
        'subscription' => 'receipt',
        'income' => 'arrow-trend-up',
        'expense' => 'arrow-trend-down',
        'handover' => 'hand-holding-dollar',
    ];

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => Day::class, 'period_from' => Day::class, 'reviewed_at' => 'datetime', 'employee_funds' => 'boolean', 'amount' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class, 'platform_account_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeApprovedIncome(Builder $query): Builder
    {
        return $query->where('status', 'approved')->where('kind', 'income');
    }

    public function scopeCustody(Builder $query): Builder
    {
        return $query->where('status', 'approved')->where('employee_funds', true)
            ->selectRaw("SUM(CASE WHEN kind IN ('subscription', 'income') THEN amount ELSE -amount END) AS held");
    }
}

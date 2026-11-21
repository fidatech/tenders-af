<?php

namespace App\Models;

use App\Casts\Day;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A cost of running the platform itself: server, domain, salaries ... paid out of one of its accounts. */
class PlatformExpense extends Model
{
    public const CATEGORIES = [
        'hosting' => 'Servers and hosting',
        'domains' => 'Domains',
        'messaging' => 'Email and SMS',
        'salaries' => 'Salaries',
        'marketing' => 'Marketing',
        'fees' => 'Payment fees',
        'office' => 'Office supplies',
        'rent' => 'Office rent',
        'food' => 'Food and refreshments',
        'utilities' => 'Utilities',
        'transport' => 'Transport',
        'other' => 'Other',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => Day::class];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class, 'platform_account_id');
    }

    public function categoryLabel(): string
    {
        return __(self::CATEGORIES[$this->category] ?? $this->category);
    }
}

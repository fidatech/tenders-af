<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One payment for sponsoring a product for a week or a month. */
class ProductPromotion extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    // period => [label, days]
    public const PERIODS = ['week' => ['One week', 7], 'month' => ['One month', 30]];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class, 'platform_account_id');
    }

    /** What sponsoring costs in one currency: ['week' => 1500.0, 'month' => 5000.0]. A period priced 0 is not offered. */
    public static function prices(string $currency): array
    {
        $prices = [];
        foreach (array_keys(self::PERIODS) as $period) {
            $price = (float) platform('sponsor_'.$period.'_'.strtolower($currency));
            if ($price > 0) {
                $prices[$period] = $price;
            }
        }

        return $prices;
    }
}

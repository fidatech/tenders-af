<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'full_access' => 'boolean', 'instant_alerts' => 'boolean', 'whatsapp_alerts' => 'boolean'];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function label(): string
    {
        return $this->name.' ('.money($this->price, $this->currency).' / '.$this->interval.')';
    }

    /** A plan that costs nothing: a business stays on it with no end date and pays nothing. */
    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    /** The free plan on offer, if there is one. Every new company starts on it. */
    public static function free(): ?self
    {
        return static::where('is_active', true)->where('price', '<=', 0)->orderBy('sort')->orderBy('id')->first();
    }

    /** The plans a company paying in this currency can choose: the free plan and the plans priced in that currency. */
    public static function offeredIn(string $currency): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('is_active', true)
            ->where(fn ($q) => $q->where('price', '<=', 0)->orWhere('currency', strtoupper($currency)))
            ->orderBy('sort')->orderBy('price')->orderBy('id');
    }

    /** What the plan gives, as lines for a price list. */
    public function features(): array
    {
        return array_values(array_filter([
            __('Free company profile in the directory'),
            $this->full_access ? __('Full tender details and documents') : __('Tender summaries and deadlines'),
            $this->instant_alerts ? __('Email alerts the moment a matching tender is published') : __('Weekly email digest of matching tenders'),
            $this->whatsapp_alerts ? __('WhatsApp alerts') : null,
            $this->full_access ? __('Bid price calculator on every tender') : null,
            $this->max_users ? trans_choice('{1} 1 user|[2,*] Up to :count users', $this->max_users, ['count' => $this->max_users]) : __('Unlimited users'),
        ]));
    }

    /** "4.99 USD" or "199 USD": two decimals only when the price has them. */
    public function priceText(): string
    {
        $price = (float) $this->price;

        return number_format($price, fmod($price, 1.0) == 0.0 ? 0 : 2).' '.$this->currency;
    }

    /** What one month on this plan costs, to tell a higher plan from a lower one whatever the period paid for. */
    public function monthlyRate(): float
    {
        return $this->interval === 'yearly' ? (float) $this->price / 12 : (float) $this->price;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A product a company offers for wholesale. Shown on the site after an administrator approved it. */
class Product extends Model
{
    public const DRAFT = 'draft';

    public const PENDING = 'pending';

    public const PUBLISHED = 'published';

    public const REJECTED = 'rejected';

    public const HIDDEN = 'hidden'; // taken off the site by an administrator

    public const STATUSES = ['draft' => 'Draft', 'pending' => 'Waiting for approval', 'published' => 'Published', 'rejected' => 'Sent back', 'hidden' => 'Hidden'];

    public const MAX_IMAGES = 6;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'made_in_afghanistan' => 'boolean',
            'sponsored_until' => 'date',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort')->orderBy('id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(ProductPromotion::class);
    }

    public static function freeSlug(string $name, ?int $except = null): string
    {
        $base = Str::limit(Str::slug($name) ?: 'product', 150, '');
        $slug = $base;
        for ($n = 2; self::where('slug', $slug)->when($except, fn (Builder $q) => $q->whereKeyNot($except))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /** Products everybody may see: approved, from a company that is not suspended. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('products.status', self::PUBLISHED)
            ->whereHas('company', fn (Builder $company) => $company->where('status', '!=', Company::SUSPENDED));
    }

    /** Sponsored products first, then the newest. */
    public function scopeSponsoredFirst(Builder $query): Builder
    {
        return $query->orderByRaw('case when sponsored_until >= ? then 0 else 1 end', [Tender::now()->toDateString()])
            ->orderByDesc('published_at')->orderByDesc('products.id');
    }

    public function scopeSponsored(Builder $query): Builder
    {
        return $query->where('sponsored_until', '>=', Tender::now()->toDateString());
    }

    public function isSponsored(): bool
    {
        return $this->sponsored_until !== null && $this->sponsored_until->toDateString() >= Tender::now()->toDateString();
    }

    /** May the company still change it? Everything but a product an administrator took off the site. */
    public function isLive(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    /** "1,200 AFN per bag", "1,200 to 1,500 AFN per bag" or "Price on request". */
    public function priceText(): string
    {
        if ($this->price_min === null) {
            return __('Price on request');
        }
        $amount = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
        $price = $this->price_max !== null && (float) $this->price_max > (float) $this->price_min
            ? $amount($this->price_min).' '.__('to').' '.$amount($this->price_max)
            : $amount($this->price_min);

        return $price.' '.$this->price_currency.' '.__('per').' '.$this->unit;
    }
}

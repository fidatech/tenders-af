<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A list of suppliers kept by one company (Supplier Favourites & Collections). Never shown to the suppliers. */
class SupplierCollection extends Model
{
    public const MAX_PER_COMPANY = 100;

    public const MAX_ITEMS = 500;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_private' => 'boolean', 'is_favourites' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierCollectionItem::class);
    }

    /** The lists this person may see: the company's shared lists and their own private ones. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('company_id', $user->company_id)
            ->where(fn (Builder $q) => $q->where('is_private', false)->orWhere('user_id', $user->id));
    }

    /** The company's "Favourites" list, made the first time it is needed. */
    public static function favouritesOf(User $user): self
    {
        return static::firstOrCreate(
            ['company_id' => $user->company_id, 'is_favourites' => true],
            ['name' => 'Favourites', 'user_id' => $user->id, 'is_private' => false],
        );
    }
}

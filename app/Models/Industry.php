<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A sector tenders and companies are sorted by: Construction, Health, ICT ... */
class Industry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(Tender::class);
    }

    /** The sectors in use, in their order: [id => name]. */
    public static function options(): array
    {
        return static::where('is_active', true)->orderBy('sort')->orderBy('name')->pluck('name', 'id')->all();
    }
}

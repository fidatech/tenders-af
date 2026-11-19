<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One of the 34 provinces of Afghanistan. */
class Province extends Model
{
    protected $guarded = [];

    public function tenders(): HasMany
    {
        return $this->hasMany(Tender::class);
    }

    /** [id => name], by name. */
    public static function options(): array
    {
        return static::orderBy('name')->pluck('name', 'id')->all();
    }
}

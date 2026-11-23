<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One change made under Admin > Module Management (see App\Support\Modules::save). */
class ModuleChange extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

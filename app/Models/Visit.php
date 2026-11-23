<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One page view of the public website (App\Support\Visitors). */
class Visit extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['visited_on' => 'date'];
    }
}

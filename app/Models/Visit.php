<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One page view of the public website (App\Support\Visitors). */
class Visit extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    // visited_on stays a plain "Y-m-d" string: a date cast would write a time with it on some
    // databases, and then a visit could not be found by its day

}

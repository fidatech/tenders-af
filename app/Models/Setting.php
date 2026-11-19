<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One setting of the platform, changed under /admin > Settings (see App\Support\Platform). */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}

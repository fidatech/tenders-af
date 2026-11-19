<?php

namespace App\Models;

use App\Casts\Day;
use Illuminate\Database\Eloquent\Model;

class CompanyMail extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['on_date' => Day::class];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'from_admin' => 'boolean', 'read_at' => 'datetime'];
    }
}

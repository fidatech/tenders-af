<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file of a tender (the RFQ form, the bill of quantities ...). Only subscribers download it. */
class TenderDocument extends Model
{
    protected $guarded = [];

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function sizeText(): string
    {
        return $this->size >= 1048576 ? number_format($this->size / 1048576, 1).' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }
}

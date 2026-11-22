<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One supplier in a collection, with the saving company's own status, note and tags. */
class SupplierCollectionItem extends Model
{
    public const STATUSES = [
        'researching' => 'Researching',
        'shortlisted' => 'Shortlisted',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'preferred' => 'Preferred',
        'rejected' => 'Rejected',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(SupplierCollection::class, 'supplier_collection_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}

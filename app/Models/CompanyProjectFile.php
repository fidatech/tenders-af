<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A picture of a project (public once it is published) or a piece of evidence (only the company and administrators). */
class CompanyProjectFile extends Model
{
    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(CompanyProject::class, 'company_project_id');
    }
}

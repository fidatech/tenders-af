<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * All businesses share one database. A model with this trait only ever shows the rows
 * of the signed-in user's business, and new rows are stamped with that business.
 * Where nobody is signed in (console commands, the admin panel) nothing is hidden,
 * so that code must say which business it means.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            if ($id = company_id()) {
                $query->where($query->getModel()->getTable().'.company_id', $id);
            }
        });

        static::creating(function ($model) {
            $model->company_id ??= company_id();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

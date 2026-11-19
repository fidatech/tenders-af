<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A calendar day with no time. It is always stored as "2026-11-14", on every database,
 * so comparing days in queries works the same on MySQL and on SQLite (the tests).
 *
 * @implements CastsAttributes<Carbon|null, mixed>
 */
class Day implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value ? Carbon::parse(substr((string) $value, 0, 10))->startOfDay() : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : Carbon::parse((string) $value)->toDateString();
    }
}

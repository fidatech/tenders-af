<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/** A notification under the bell of one administrator. */
class AdminNotice extends Model
{
    // kind => icon
    public const KINDS = [
        'company' => 'building', 'verification' => 'shield-halved', 'tender' => 'gavel',
        'product' => 'boxes-stacked', 'payment' => 'money-bill-wave', 'message' => 'envelope', 'info' => 'bell',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function icon(): string
    {
        return self::KINDS[$this->kind] ?? 'bell';
    }

    /**
     * Tell every active administrator. A notification must never break what the visitor
     * was doing, so a failure is only reported.
     */
    public static function toAll(string $kind, string $title, ?string $body = null, ?string $url = null): void
    {
        try {
            $now = now();
            $rows = Admin::where('is_active', true)->pluck('id')->map(fn ($id) => [
                'admin_id' => $id, 'kind' => $kind, 'title' => mb_substr($title, 0, 250),
                'body' => $body === null ? null : mb_substr($body, 0, 250), 'url' => $url,
                'created_at' => $now, 'updated_at' => $now,
            ])->all();
            if ($rows) {
                self::insert($rows);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}

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

    public static function messageToAll(string $title, string $body, string $replyEmail): bool
    {
        $address = str_replace('%40', '@', rawurlencode($replyEmail));

        return self::toAll('message', $title, $body, 'mailto:'.$address);
    }

    /** Returns true when notifications were delivered to active administrators. */
    public static function toAll(string $kind, string $title, ?string $body = null, ?string $url = null): bool
    {
        try {
            $now = now();
            $rows = Admin::where('is_active', true)->pluck('id')->map(fn ($id) => [
                'admin_id' => $id, 'kind' => $kind, 'title' => mb_substr($title, 0, 250),
                'body' => $body === null ? null : mb_substr($body, 0, 250),
                'full_body' => $body !== null && mb_strlen($body) > 250 ? $body : null,
                'url' => $url,
                'created_at' => $now, 'updated_at' => $now,
            ])->all();
            return $rows && self::insert($rows);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}

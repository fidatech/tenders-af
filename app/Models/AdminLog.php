<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/** What an administrator did, and when. */
class AdminLog extends Model
{
    protected $guarded = [];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** Write one line. A problem with the log never stops the work itself. */
    public static function record(string $action, ?string $subject = null, ?string $details = null): void
    {
        try {
            $admin = auth('admin')->user();
            static::create([
                'admin_id' => $admin?->id,
                'admin_name' => $admin?->name,
                'action' => $action,
                'subject' => $subject ? mb_substr($subject, 0, 250) : null,
                'details' => $details,
                'ip' => request()?->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}

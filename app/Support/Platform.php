<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * Settings of the platform. A value saved under /admin > Settings wins; otherwise the
 * value of config/tenders.php (which reads .env) is used, so a fresh installation works
 * before anything is saved.
 */
class Platform
{
    /** What the Settings page offers, with the value used when nothing is saved and config has none. */
    public const DEFAULTS = [
        'trial_days' => 7,
        'registration' => '1',
        'posting_free' => '1',
        'billing_email' => '',
        'support_email' => '',
        'support_whatsapp' => '',
        'trial_warn_days' => '2',
        'renew_warn_days' => '7, 1',
        'renew_open_days' => 30,
        'receipt_prefix' => 'TA-R',
        'company_name' => '',
        'company_address' => '',
        'whatsapp_url' => '',
        'whatsapp_token' => '',
    ];

    public static function all(): array
    {
        if (! app()->bound('platform.settings')) {
            try {
                $saved = Setting::pluck('value', 'key')->all();
            } catch (Throwable) {
                $saved = []; // the table is not there yet (before the first migration)
            }
            app()->instance('platform.settings', $saved);
        }

        return app('platform.settings');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $saved = self::all();
        if (array_key_exists($key, $saved) && $saved[$key] !== null) {
            return $saved[$key];
        }

        return config('tenders.'.$key) ?? self::DEFAULTS[$key] ?? $default;
    }

    public static function bool(string $key): bool
    {
        return filter_var(self::get($key), FILTER_VALIDATE_BOOL);
    }

    /** "7, 1" becomes [7, 1]: numbers of days, biggest first. */
    public static function days(string $key): array
    {
        $days = array_map('intval', preg_split('/[\s,;]+/', (string) self::get($key), -1, PREG_SPLIT_NO_EMPTY));
        $days = array_values(array_unique(array_filter($days, fn (int $day) => $day > 0)));
        rsort($days);

        return $days;
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value === null ? '' : (string) $value]);
        }
        app()->forgetInstance('platform.settings');
    }
}

<?php

namespace App\Support;

use Illuminate\Http\Request;
use Locale;
use MaxMind\Db\Reader;
use Throwable;

/**
 * The country and city of an IP address, looked up on the server in a local database file
 * (config/geoip.php), read with App\Support\MmdbReader (or the maxmind-db/reader library when it
 * is installed). Without the file every visit is counted with an unknown place; nothing breaks.
 */
class GeoIp
{
    /** @var Reader|false|null null: not opened yet; false: not available */
    private static $reader = null;

    /** Is the lookup working: the reader library is installed and the database file is there? */
    public static function available(): bool
    {
        return self::reader() !== false;
    }

    /** @return array{country: ?string, region: ?string, city: ?string} */
    public static function locate(Request $request): array
    {
        $place = ['country' => null, 'region' => null, 'city' => null];
        $ip = (string) $request->ip();

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) && ($reader = self::reader())) {
            try {
                $record = $reader->get($ip);
                if (is_array($record)) {
                    $place['country'] = isset($record['country']['iso_code']) ? strtoupper($record['country']['iso_code']) : null;
                    $place['region'] = $record['subdivisions'][0]['names']['en'] ?? null;
                    $place['city'] = $record['city']['names']['en'] ?? null;
                }
            } catch (Throwable) {
                // a damaged or unreadable file: count the visit without a place
            }
        }

        if (! $place['country'] && config('geoip.trust_cloudflare')) {
            $code = strtoupper((string) $request->header('CF-IPCountry'));
            $place['country'] = preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, ['XX', 'T1'], true) ? $code : null;
        }

        $place['region'] = $place['region'] ? mb_substr($place['region'], 0, 100) : null;
        $place['city'] = $place['city'] ? mb_substr($place['city'], 0, 100) : null;

        return $place;
    }

    /** The English name of a country code: "AF" → "Afghanistan". Never an empty string. */
    public static function countryName(?string $code): string
    {
        if (! $code) {
            return 'Unknown';
        }
        $code = strtoupper($code);
        if (class_exists(Locale::class)) {
            $name = Locale::getDisplayRegion('-'.$code, 'en');
            if ($name !== '' && $name !== $code && strcasecmp($name, 'Unknown Region') !== 0) {
                return $name;
            }
        }

        return config('countries')[$code] ?? $code;
    }

    /** The flag of a country as an emoji, made from its code ("AF" → 🇦🇫). */
    public static function flag(?string $code): string
    {
        if (! $code || ! preg_match('/^[A-Z]{2}$/', strtoupper($code))) {
            return '🌐';
        }

        return implode('', array_map(fn ($letter) => mb_chr(0x1F1E6 + ord($letter) - 65), str_split(strtoupper($code))));
    }

    /** For tests: forget the opened reader. */
    public static function reset(): void
    {
        self::$reader = null;
    }

    private static function reader()
    {
        if (self::$reader === null) {
            $file = (string) config('geoip.database');
            try {
                // the maxmind-db/reader library when it is installed, otherwise the reader built into the app
                self::$reader = ! is_file($file) ? false
                    : (class_exists(Reader::class) ? new Reader($file) : new MmdbReader($file));
            } catch (Throwable) {
                self::$reader = false;
            }
        }

        return self::$reader;
    }
}

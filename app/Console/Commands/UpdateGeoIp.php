<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads the free "IP to City Lite" database of DB-IP (CC BY 4.0) used to find the country and
 * city of visitors. A new file comes out every month; the scheduler runs this every month and the
 * deployment runs it when the file is missing. The old file stays in place until the new one is whole.
 */
class UpdateGeoIp extends Command
{
    protected $signature = 'geoip:update {--force : Download even when the file is less than a month old}';

    protected $description = 'Download the IP location database for the visitor figures';

    public function handle(): int
    {
        $target = (string) config('geoip.database');
        if (! $this->option('force') && is_file($target) && filemtime($target) > now()->subDays(28)->getTimestamp()) {
            $this->info('The location database is up to date.');

            return self::SUCCESS;
        }
        @mkdir(dirname($target), 0775, true);
        $gz = $target.'.download.gz';
        $tmp = $target.'.download';

        // this month's file appears in the first days of the month: try it, then last month's
        foreach ([now()->format('Y-m'), now()->subMonthNoOverflow()->format('Y-m')] as $month) {
            $url = str_replace('{month}', $month, (string) config('geoip.download_url'));
            try {
                $response = Http::timeout(300)->withOptions(['sink' => $gz])->get($url);
                if (! $response->successful() || filesize($gz) < 1024 * 100) {
                    $this->warn("Not available: $url (HTTP {$response->status()})");

                    continue;
                }
                $in = gzopen($gz, 'rb');
                $out = fopen($tmp, 'wb');
                while (! gzeof($in)) {
                    fwrite($out, gzread($in, 1024 * 1024));
                }
                gzclose($in);
                fclose($out);
                // a whole MaxMind DB file ends with its metadata marker
                $tail = file_get_contents($tmp, false, null, max(0, filesize($tmp) - 128 * 1024));
                if (! str_contains((string) $tail, "\xAB\xCD\xEFMaxMind.com")) {
                    throw new \RuntimeException('The downloaded file is not a location database.');
                }
                rename($tmp, $target);
                @unlink($gz);
                $this->info("Location database updated from $url (".round(filesize($target) / 1048576, 1).' MB).');

                return self::SUCCESS;
            } catch (Throwable $e) {
                $this->warn("Could not use $url: ".$e->getMessage());
            } finally {
                @unlink($gz);
                @unlink($tmp);
            }
        }
        $this->error('The location database could not be downloaded. Visitors are still counted, with an unknown place.');

        return self::FAILURE;
    }
}

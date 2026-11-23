<?php

namespace App\Support;

use App\Models\Tender;
use App\Models\Visit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counting the visitors of the public website, without cookies and without keeping IP addresses.
 *
 * A visitor is a code made from the day, the address and the browser with the application key; it
 * cannot be turned back into the address, and the same person gets a new code the next day. Robots,
 * prefetches, administrators and browsers that ask not to be tracked (Do Not Track, Global Privacy
 * Control) are not counted.
 */
class Visitors
{
    private const BOTS = '/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|preview|headless|lighthouse|pagespeed|curl|wget|python|httpclient|http-client|java\/|okhttp|axios|node-fetch|go-http|scrapy|monitor|uptime|pingdom|semrush|ahrefs|mj12|dataprovider|bingpreview|whatsapp|telegram/i';

    public static function shouldCount(Request $request, Response $response): bool
    {
        $agent = (string) $request->userAgent();

        return $request->isMethod('GET')
            && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $request->ajax()
            && $agent !== '' && ! preg_match(self::BOTS, $agent)
            && $request->header('DNT') !== '1' && $request->header('Sec-GPC') !== '1'
            && ! in_array(strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose'))), ['prefetch', 'prefetch;prerender', 'preview'], true)
            && ! auth('admin')->check();
    }

    public static function record(Request $request): void
    {
        try {
            $day = Tender::now()->toDateString(); // Kabul time, like every date of the site
            $place = GeoIp::locate($request);
            $referrer = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
            $referrer = $referrer && strcasecmp($referrer, $request->getHost()) !== 0 ? mb_substr(preg_replace('/^www\./i', '', $referrer), 0, 100) : null;

            Visit::create([
                'visited_on' => $day,
                'visitor' => substr(hash_hmac('sha256', $day.'|'.$request->ip().'|'.$request->userAgent(), (string) config('app.key')), 0, 16),
                'country' => $place['country'],
                'region' => $place['region'],
                'city' => $place['city'],
                'page' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                'referrer' => $referrer,
                'device' => preg_match('/Mobi|Android|iPhone|iPad/i', (string) $request->userAgent()) ? 'mobile' : 'desktop',
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e); // a visit that could not be counted never breaks the page
        }
    }
}

<?php

namespace App\Support;

use App\Models\Tender;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counting the visitors of the public website, without cookies and without keeping IP addresses.
 *
 * Every view of a public page is written down as one of three kinds:
 *  - bot: a known robot or crawler (App\Support\Robots), with its kind and name
 *  - human: a person. A view starts as "pending"; a small script in the page tells the server that
 *    it ran in a real browser (beacon()), which makes it, and the person's next pages, "human".
 *    Scripts and most robots never run it.
 *  - pending: never confirmed. Shown on Admin > Growth as unconfirmed traffic, never on the public page.
 *
 * A visitor is a code made from the day, the address and the browser with the application key; it
 * cannot be turned back into the address, and it changes every day. Prefetches, administrators and
 * browsers that ask not to be tracked (Do Not Track, Global Privacy Control) are not counted at all.
 */
class Visitors
{
    /** How long a confirmation counts for the next pages of the same visitor, in seconds. */
    public const CONFIRMED_FOR = 1800;

    /** Routes robots are counted on besides the pages: what crawlers read to find the pages. */
    public const ROBOT_FILES = ['site.sitemap', 'site.robots', 'site.llms'];

    public static function shouldCount(Request $request, Response $response, bool $robotFile = false): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200 || $request->ajax() || auth('admin')->check()) {
            return false;
        }
        if (in_array(strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose'))), ['prefetch', 'prefetch;prerender', 'preview'], true)) {
            return false;
        }
        $robot = Robots::detect($request->userAgent());
        // sitemap, robots.txt and llms.txt are counted for robots only
        if ($robotFile) {
            return $robot !== null;
        }
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        return $robot !== null || ($request->header('DNT') !== '1' && $request->header('Sec-GPC') !== '1');
    }

    /** The daily code of the person (or program) behind a request. */
    public static function code(Request $request, ?string $day = null): string
    {
        $day ??= Tender::now()->toDateString();

        return substr(hash_hmac('sha256', $day.'|'.$request->ip().'|'.$request->userAgent(), (string) config('app.key')), 0, 16);
    }

    public static function record(Request $request): void
    {
        try {
            $day = Tender::now()->toDateString(); // Kabul time, like every date of the site
            $visitor = self::code($request, $day);
            $robot = Robots::detect($request->userAgent());
            $place = GeoIp::locate($request);
            $referrer = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
            $referrer = $referrer && strcasecmp($referrer, $request->getHost()) !== 0 ? mb_substr(preg_replace('/^www\./i', '', $referrer), 0, 100) : null;

            Visit::create([
                'visited_on' => $day,
                'visitor' => $visitor,
                'country' => $place['country'],
                'region' => $place['region'],
                'city' => $place['city'],
                'page' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                'referrer' => $referrer,
                'device' => preg_match('/Mobi|Android|iPhone|iPad/i', (string) $request->userAgent()) ? 'mobile' : 'desktop',
                'kind' => $robot ? 'bot' : (Cache::has('visitor-human:'.$visitor) ? 'human' : 'pending'),
                'bot_type' => $robot['type'] ?? null,
                'bot_name' => $robot['name'] ?? null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e); // a visit that could not be counted never breaks the page
        }
    }

    /**
     * The page's script ran in a real browser: the visitor's views of the last half hour become
     * "human", and so do their next pages for half an hour. A known robot is never confirmed.
     */
    public static function beacon(Request $request): void
    {
        if (Robots::detect($request->userAgent()) !== null) {
            return;
        }
        $day = Tender::now()->toDateString();
        $visitor = self::code($request, $day);
        Cache::put('visitor-human:'.$visitor, true, self::CONFIRMED_FOR);
        Visit::where('visited_on', $day)->where('visitor', $visitor)->where('kind', 'pending')
            ->where('created_at', '>=', now()->subSeconds(self::CONFIRMED_FOR))
            ->update(['kind' => 'human']);
    }
}

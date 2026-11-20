<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Other addresses of the site (tender.af, www.tender.af, www.tenders.af) answer with a
 * permanent redirect to the one address in APP_URL, keeping the path and the query. One
 * address keeps search engines from seeing the same pages twice.
 */
class CanonicalHost
{
    public const ALIASES = ['tender.af', 'www.tender.af', 'www.tenders.af'];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array(strtolower($request->getHost()), self::ALIASES, true)) {
            return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}

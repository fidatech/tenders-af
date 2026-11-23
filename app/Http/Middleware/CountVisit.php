<?php

namespace App\Http\Middleware;

use App\Support\Visitors;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts a view of a public page (App\Support\Visitors). The counting happens after the page has
 * been sent, so the visitor never waits for it.
 */
class CountVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $name = (string) $request->route()?->getName();
        if (str_starts_with($name, 'site.') && ! in_array($name, ['site.sitemap', 'site.robots', 'site.llms', 'site.manifest', 'site.sw', 'site.offline', 'site.companies.logo', 'site.companies.cover', 'site.projects.image', 'site.statistics.csv'], true)
            && Visitors::shouldCount($request, $response)) {
            Visitors::record($request);
        }
    }
}

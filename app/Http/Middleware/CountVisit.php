<?php

namespace App\Http\Middleware;

use App\Support\Visitors;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts a view of a public page, by a person or a robot (App\Support\Visitors). The counting
 * happens after the page has been sent, so the visitor never waits for it.
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
        if (! str_starts_with($name, 'site.') || in_array($name, ['site.manifest', 'site.sw', 'site.offline', 'site.companies.logo', 'site.companies.cover', 'site.projects.image', 'site.statistics.csv', 'site.beacon'], true)) {
            return;
        }
        // the sitemap, robots.txt and llms.txt are counted for robots: they show which crawlers read the site
        if (Visitors::shouldCount($request, $response, in_array($name, Visitors::ROBOT_FILES, true))) {
            Visitors::record($request);
        }
    }
}

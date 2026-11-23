<?php

namespace App\Http\Middleware;

use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware "module:directory": the page belongs to a module and opens only while the
 * module is on. Nothing is deleted when it is off; the page is simply not offered.
 *
 *  - a company's panel: back to the dashboard with a short note
 *  - the public website: an explanatory page with status 404 and noindex, so search engines drop
 *    the address for now and pick it up again when the module is switched back on
 *  - forms and scripts: 404
 */
class EnsureModule
{
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        foreach ($modules as $module) {
            if (Modules::enabled($module)) {
                continue;
            }
            if ($request->expectsJson() || ! $request->isMethodSafe()) {
                abort(404);
            }
            if ($request->user('web') && ! $request->routeIs('site.*') && $request->route()?->getName() !== 'dashboard') {
                return redirect()->route('dashboard')->with('warning', __(':module is not available at the moment.', ['module' => __(Modules::definition($module)['name'])]));
            }

            return response()->view('errors.module-off', ['module' => Modules::definition($module)], 404);
        }

        return $next($request);
    }
}

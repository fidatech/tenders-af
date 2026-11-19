<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended company sees one page that says so. A company whose paid time ended keeps its
 * panel and its profile: it only loses the full tenders and the alerts until it pays again.
 */
class EnsureCompanyActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user()?->company;

        if ($company && $company->status === \App\Models\Company::SUSPENDED) {
            return response()->view('errors.suspended', ['company' => $company], 402);
        }

        return $next($request);
    }
}

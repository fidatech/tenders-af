<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');
        $name = (string) $request->route()?->getName();

        if ($admin && $name === 'admin.dashboard' && $admin->homeRoute() !== $name && $admin->is_active !== false) {
            return redirect()->route($admin->homeRoute());
        }
        abort_unless($admin && AdminAccess::allows($admin, $name, $request->isMethodSafe()), 403);

        return $next($request);
    }
}

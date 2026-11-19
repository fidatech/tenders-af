<?php

namespace App\Http\Middleware;

use App\Exceptions\UserError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * What each role of an administrator may do:
 *   super    everything
 *   manager  everything except administrators, settings, the activity log and deleting a company
 *   viewer   may look at everything a manager sees, and change nothing
 * Everyone may sign out and change his own account.
 */
class AdminRole
{
    private const SUPER_ONLY = ['admin.admins.*', 'admin.settings.*', 'admin.logs.*', 'admin.companies.destroy'];

    private const OWN = ['admin.logout', 'admin.profile.edit', 'admin.profile.update'];

    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();
        $name = (string) $request->route()?->getName();

        if (! $admin || $admin->isSuper() || in_array($name, self::OWN, true)) {
            return $next($request);
        }
        if (Str::is(self::SUPER_ONLY, $name)) {
            abort(403, __('Only a super administrator can open this.'));
        }
        if ($admin->role === 'viewer' && ! $request->isMethodSafe()) {
            throw new UserError(__('Your role can look at everything but cannot change it.'));
        }

        return $next($request);
    }
}

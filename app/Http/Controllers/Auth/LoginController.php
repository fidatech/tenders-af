<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Wrong passwords allowed per email and IP address before a one-minute pause. */
    private const MAX_ATTEMPTS = 5;

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = Str::lower($credentials['email']);

        $key = Str::transliterate($credentials['email'].'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        if (! Auth::guard('web')->attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('The email or the password is not right.')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        // a page of the admin panel saved earlier in this browser is not where a company goes:
        // it would send the company to the admin sign-in page
        if (self::isAdminUrl($request->session()->get('url.intended'))) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route('dashboard'));
    }

    /** True for an address of the admin panel (/admin and everything under it). */
    public static function isAdminUrl(?string $url): bool
    {
        return $url !== null && preg_match('#^/admin(/|$)#', (string) parse_url($url, PHP_URL_PATH)) === 1;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        // only this sign-in ends: an administrator signed in at /admin in the same browser stays
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

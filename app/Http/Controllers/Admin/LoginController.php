<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Sign-in of the system administrators. It is separate from the sign-in of the businesses. */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function show(): View
    {
        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = Str::lower($credentials['email']);

        $key = Str::transliterate('admin|'.$credentials['email'].'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        if (! Auth::guard('admin')->attempt($credentials + ['is_active' => true])) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('The email or the password is not right.')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        \App\Models\AdminLog::record('signed in');

        $admin = Auth::guard('admin')->user();

        return $admin->homeRoute() !== 'admin.dashboard'
            ? redirect()->route($admin->homeRoute())
            : redirect()->intended(route($admin->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

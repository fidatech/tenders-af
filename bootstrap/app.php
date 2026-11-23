<?php

use App\Exceptions\UserError;
use App\Http\Middleware\AdminRole;
use App\Http\Middleware\CanonicalHost;
use App\Http\Middleware\CompressResponse;
use App\Http\Middleware\EnsureCompanyActive;
use App\Http\Middleware\EnsureModule;
use App\Http\Middleware\EnsureOwner;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\StripNumberCommas;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // the businesses and the system administrators each have their own sign-in page
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.dashboard') : route('dashboard'));

        $middleware->web(append: [StripNumberCommas::class, EnsureUserIsActive::class, \App\Http\Middleware\CountVisit::class]);
        // tender.af and the www addresses lead to tenders.af
        $middleware->prepend(CanonicalHost::class);
        $middleware->prepend(CompressResponse::class);
        $middleware->alias(['company.active' => EnsureCompanyActive::class, 'owner' => EnsureOwner::class, 'admin.role' => AdminRole::class, 'module' => EnsureModule::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A problem the user can fix: go back to the form and show the message.
        $exceptions->render(function (UserError $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('danger', $e->getMessage());
        });
        // the files sent together are larger than the server takes in one go
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $message = __('The files are too large to send together. Send fewer files at a time, or make them smaller.');
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return back()->with('danger', $message);
        });
        $exceptions->dontReport(UserError::class);
    })->create();

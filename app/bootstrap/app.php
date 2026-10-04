<?php

use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackVisitor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        // The app has no "login" route (Filament names its own). Guests hitting
        // 'auth' routes, such as the OAuth consent screen at /oauth/authorize,
        // are sent to the dashboard sign-in instead of a "Route [login]" error.
        $middleware->redirectGuestsTo(fn () => route('filament.dashboard.auth.login'));
        $middleware->web(append: [
            SetLocale::class,
            TrackVisitor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\RunPluginRequestPipeline::class,
        ]);

        // Prepend Sanctum's stateful-domain middleware to the API group.
        // This allows the Inertia SPA to authenticate API routes via the
        // existing PHP session cookie, while external clients can still
        // use a Bearer token (Personal Access Token).
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\RunPluginRequestPipeline::class,
        ]);

        $middleware->alias([
            'session.authenticated' => \App\Http\Middleware\EnsureSessionAuthenticated::class,
        ]);
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: '*',
            headers:
                Request::HEADER_FORWARDED |
                Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_HOST
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

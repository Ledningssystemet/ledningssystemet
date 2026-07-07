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

        $middleware->api(append: [
            \App\Http\Middleware\RunPluginRequestPipeline::class,
        ]);

        $middleware->alias([
            'session.authenticated' => \App\Http\Middleware\EnsureSessionAuthenticated::class,
        ]);

        $middleware->trustProxies(
            at: '*',
            headers:
                Request::HEADER_FORWARDED |
                Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_HOST
        );

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

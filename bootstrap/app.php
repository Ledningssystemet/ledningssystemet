<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Ledningssystemet\Ledningssystemet\Http\Middleware\EncryptCookies;
use Ledningssystemet\Ledningssystemet\Http\Middleware\PreventRequestsDuringMaintenance;
use Ledningssystemet\Ledningssystemet\Http\Middleware\TrimStrings;
use Ledningssystemet\Ledningssystemet\Http\Middleware\TrustProxies;
use Ledningssystemet\Ledningssystemet\Http\Middleware\VerifyCsrfToken;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, TrustProxies::class);
        $middleware->replace(
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            PreventRequestsDuringMaintenance::class,
        );
        $middleware->replace(\Illuminate\Foundation\Http\Middleware\TrimStrings::class, TrimStrings::class);
        $middleware->web(
            replace: [
                \Illuminate\Cookie\Middleware\EncryptCookies::class => EncryptCookies::class,
                \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class => VerifyCsrfToken::class,
            ],
        );

        $middleware->statefulApi();
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

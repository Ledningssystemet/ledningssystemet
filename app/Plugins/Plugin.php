<?php

namespace App\Plugins;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

abstract class Plugin
{
    public function register(Application $app): void
    {
    }

    public function boot(Application $app): void
    {
    }

    /**
     * @param  array<string, mixed>  $shared
     * @return array<string, mixed>
     */
    public function extendInertiaShared(array $shared, Request $request): array
    {
        return $shared;
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<int, array<string, mixed>>
     */
    public function extendMenu(array $categories, ?Request $request = null): array
    {
        return $categories;
    }

    /**
     * @return array<string, mixed>
     */
    public function frontendConfig(?Request $request = null): array
    {
        return [];
    }

    public function handleRequest(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}


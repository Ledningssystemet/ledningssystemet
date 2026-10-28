<?php

namespace Ledningssystemet\Ledningssystemet\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ledningssystemet\Ledningssystemet\Support\Modules;
use Symfony\Component\HttpFoundation\Response;

class ModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_if(Modules::inactive($module), 404);

        return $next($request);
    }
}

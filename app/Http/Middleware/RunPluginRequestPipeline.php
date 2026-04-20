<?php

namespace App\Http\Middleware;

use App\Plugins\PluginRuntime;
use Closure;
use Illuminate\Http\Request;

class RunPluginRequestPipeline
{
    public function __construct(private readonly PluginRuntime $pluginRuntime)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        return $this->pluginRuntime->handleRequest($request, $next);
    }
}


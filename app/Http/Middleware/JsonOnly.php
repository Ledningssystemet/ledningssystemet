<?php

namespace App\Http\Middleware;
use \Closure;

class JsonOnly
{
    /**
     * We only accept json
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $acceptHeader = (string) $request->header('Accept', '');
        if (false === strpos($acceptHeader, 'application/json') && false === strpos($acceptHeader, 'text/event-stream')) {
           abort(406, 'This endpoint only support application/json responses');
        }

        return $next($request);
    }
}

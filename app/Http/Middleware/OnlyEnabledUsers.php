<?php

namespace App\Http\Middleware;
use \Closure;

class OnlyEnabledUsers
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
       if((null != request()->user()) &&
         (!request()->user()->enabled))
         abort(401);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthProxy
{
   /**
   * Handle an incoming request. This middleware is applied to all routes and is used to validate user authorization before proceeding.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
   * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
   */
   public function handle(Request $request, Closure $next)
   {
      // Only apply if authenticated
      if(!Auth::check())
         abort(401);

      // Ensure user is still enabled
      if(!Auth::user()->enabled)
      {
         Auth::logout();
         abort(401);
      }
       
      // Proceed
      return $next($request);
   }
}

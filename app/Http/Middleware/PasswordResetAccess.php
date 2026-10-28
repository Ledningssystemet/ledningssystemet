<?php

namespace Ledningssystemet\Ledningssystemet\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PasswordResetAccess
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->hasPermissionTo('systemadministrator.edit')) {
            return $next($request);
        }

        if (!config('ledningssystemet.login_allow_passwordlogin')) {
            abort(403);
        }

        return app(RedirectIfAuthenticated::class)->handle($request, $next);
    }
}

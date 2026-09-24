<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireMfaEnforcement
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('ledningssystemet.mfa_enabled') || !config('ledningssystemet.mfa_enforced')) {
            return $next($request);
        }

        if (!$request->user()) {
            return $next($request);
        }

        $user = $request->user();
        $hasMfaConfigured = !empty($user->two_factor_secret)
            && !empty($user->two_factor_recovery_codes)
            && !empty($user->two_factor_confirmed_at);
        if ($hasMfaConfigured) {
            return $next($request);
        }

        if ($request->routeIs('mfa.enforcement') || $request->is('user/two-factor-authentication') || $request->is('user/confirmed-two-factor-authentication')) {
            return $next($request);
        }

        return redirect()->route('mfa.enforcement');
    }
}

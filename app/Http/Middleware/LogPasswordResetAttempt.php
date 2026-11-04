<?php

namespace Ledningssystemet\Ledningssystemet\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogPasswordResetAttempt
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('post')) {
            $email = $request->input('email');

            Log::info('Password reset attempt', [
                'action' => $request->routeIs('password.email') ? 'request_link' : 'reset_password',
                'email' => is_string($email) ? mb_substr($email, 0, 254) : null,
                'ip' => $request->ip(),
            ]);
        }

        return $next($request);
    }
}

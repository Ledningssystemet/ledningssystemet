<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionAuthenticated
{
    /**
     * Allow only first-party session-authenticated requests.
     *
     * Requests authenticated via Personal Access Token are denied.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => __('api.errors.unauthenticated'),
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if ($request->bearerToken() !== null) {
            return response()->json([
                'message' => __('api.errors.session_auth_required'),
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

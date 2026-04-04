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
                'message' => 'Unauthenticated.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if ($user->currentAccessToken() !== null) {
            return response()->json([
                'message' => 'This endpoint requires session authentication.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}


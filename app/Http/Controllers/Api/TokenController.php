<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tokens = $user->tokens()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

        return response()->json([
            'data' => $tokens,
            'current_access_token_id' => $request->user()->currentAccessToken()?->id,
        ]);
    }

    /**
     * Issue a new Personal Access Token for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', 'max:255'],
            'expires_at' => ['sometimes', 'date'],
        ]);

        $abilities = $validated['abilities'] ?? ['*'];
        $expiresAt = isset($validated['expires_at'])
            ? CarbonImmutable::parse($validated['expires_at'])
            : null;

        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw ValidationException::withMessages([
                'expires_at' => [__('api.tokens.expires_at_future')],
            ]);
        }

        $newToken = $request->user()->createToken(
            $validated['name'],
            $abilities,
            $expiresAt,
        );

        return response()->json([
            'token' => $newToken->plainTextToken,
            'token_id' => $newToken->accessToken->id,
            'name' => $newToken->accessToken->name,
            'abilities' => $newToken->accessToken->abilities,
            'expires_at' => $newToken->accessToken->expires_at,
            'created_at' => $newToken->accessToken->created_at,
        ], 201);
    }

    /**
     * Revoke one of the authenticated user's tokens.
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->findOrFail($tokenId);
        $token->delete();

        return response()->json(status: 204);
    }

    /**
     * Revoke the currently used access token.
     */
    public function destroyCurrent(Request $request): JsonResponse
    {
        $currentToken = $request->user()->currentAccessToken();

        if ($currentToken === null) {
            return response()->json([
                'message' => __('api.tokens.no_current_access_token'),
            ], 400);
        }

        $currentToken->delete();

        return response()->json(status: 204);
    }
}


<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionStatusController extends Controller
{
    /**
     * Lightweight heartbeat endpoint used to keep the session alive.
     */
    public function show(Request $request): JsonResponse
    {

        return response()->json([
            'authenticated' => $request->user() !== null,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}


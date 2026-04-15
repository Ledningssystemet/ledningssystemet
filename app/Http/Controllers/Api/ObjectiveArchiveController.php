<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Objective;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ObjectiveArchiveController extends Controller
{
    public function __invoke(Request $request, Objective $objective): JsonResponse
    {
        Gate::authorize('update', $objective);

        $user = $request->user();
        if (
            $user
            && $objective->responsible_user_id !== null
            && (int) $objective->responsible_user_id !== (int) $user->id
        ) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if ($objective->archived_at === null) {
            $objective->archived_at = now();
            $objective->save();
        }

        return response()->json($objective->fresh());
    }
}


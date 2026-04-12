<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AgreementArchiveController extends Controller
{
    public function __invoke(Request $request, Agreement $agreement): JsonResponse
    {
        Gate::authorize('update', $agreement);

        $user = $request->user();
        if (
            $user
            && $agreement->responsible_user_id !== null
            && (int) $agreement->responsible_user_id !== (int) $user->id
        ) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if ($agreement->archived_at === null) {
            $agreement->archived_at = now();
            $agreement->save();
        }

        return response()->json($agreement->fresh());
    }
}


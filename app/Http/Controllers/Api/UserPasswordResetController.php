<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class UserPasswordResetController extends Controller
{
    public function store(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        if (filled($user->external_id)) {
            return response()->json([
                'status' => 'not_supported',
                'message' => __('pages.users.password_reset_external_not_supported'),
            ], 422);
        }

        $status = Password::sendResetLink(['email' => $user->email]);
        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => 'sent',
                'message' => __('pages.users.password_reset_sent'),
            ]);
        }

        return response()->json([
            'status' => 'failed',
            'message' => __($status),
        ], 422);
    }
}


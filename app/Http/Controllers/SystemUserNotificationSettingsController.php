<?php

namespace Ledningssystemet\Ledningssystemet\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Ledningssystemet\Ledningssystemet\Models\User;

class SystemUserNotificationSettingsController extends Controller
{
    public function show(User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        return response()->json($user->getUserCommunicationPreferences());
    }

    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'monday' => ['required', 'boolean'],
            'tuesday' => ['required', 'boolean'],
            'wednesday' => ['required', 'boolean'],
            'thursday' => ['required', 'boolean'],
            'friday' => ['required', 'boolean'],
            'saturday' => ['required', 'boolean'],
            'sunday' => ['required', 'boolean'],
        ]);

        $preferences = array_map(fn ($value) => (bool) $value, $validated);

        return response()->json($user->setUserCommunicationPreferences($preferences));
    }
}

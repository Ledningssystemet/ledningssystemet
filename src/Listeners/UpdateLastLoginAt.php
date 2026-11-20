<?php

namespace Ledningssystemet\Ledningssystemet\Listeners;

use Illuminate\Auth\Events\Login;
use Ledningssystemet\Ledningssystemet\Models\User;

class UpdateLastLoginAt
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user = $event->user;
        $lastLoginAt = now();

        // Avoid user save hooks that may access the guard while login is in progress.
        $user->newQuery()->whereKey($user->getKey())->toBase()->update([
            'last_login_at' => $lastLoginAt,
        ]);

        $user->setAttribute('last_login_at', $lastLoginAt);
        $user->syncOriginalAttribute('last_login_at');
    }
}

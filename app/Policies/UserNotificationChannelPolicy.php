<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserNotificationChannel;

class UserNotificationChannelPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserNotificationChannel $userNotificationChannel = new UserNotificationChannel): bool
    {
        return (($userNotificationChannel->user_id == $user->id) || $user->haveAnyAccessRights(['systemadministrator.edit']));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserNotificationChannel $userNotificationChannel = new UserNotificationChannel): bool
    {
        return (($userNotificationChannel->user_id == $user->id) || $user->haveAnyAccessRights(['systemadministrator.edit']));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserNotificationChannel $userNotificationChannel = new UserNotificationChannel): bool
    {
                return $user->can('update', $userNotificationChannel);
    }
}

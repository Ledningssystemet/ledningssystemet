<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Activity $activity = new Activity): bool
    {
        return (($activity->responsible_user_id == $user->id) || $user->haveAnyAccessRights(['managementtools.edit']));
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
    public function update(User $user, Activity $activity = new Activity): bool
    {
        return (($activity->responsible_user_id == $user->id) || $user->haveAnyAccessRights(['managementtools.edit']));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Activity $activity = new Activity): bool
    {
        return $user->can('update', $activity);
    }
}

<?php

namespace App\Policies;

use App\Models\ActivityFlow;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ActivityFlowPolicy
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
    public function view(User $user, ActivityFlow $activityFlow = new ActivityFlow): bool
    {
        return (($activityFlow->responsible_user_id == $user->id) || $user->haveAnyAccessRights(['managementtools.edit']));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ActivityFlow $activityFlow = new ActivityFlow): bool
    {
        return (($activityFlow->responsible_user_id == $user->id) || $user->haveAnyAccessRights(['managementtools.edit']));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ActivityFlow $activityFlow = new ActivityFlow): bool
    {
                return $user->can('update', $activityFlow);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ActivityFlow $activityFlow = new ActivityFlow): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ActivityFlow $activityFlow = new ActivityFlow): bool
    {
        return false;
    }
}

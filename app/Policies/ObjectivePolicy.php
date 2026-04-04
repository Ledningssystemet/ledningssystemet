<?php

namespace App\Policies;

use App\Models\Objective;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ObjectivePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['objectives.read', 'objectives.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Objective $objective = new Objective): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['objectives.read', 'objectives.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['objectives.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Objective $objective = new Objective): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        if (null == $objective->archived_at)
            return $user->haveAnyAccessRights(['objectives.edit']);

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Objective $objective = new Objective): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->can('update', $objective);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Objective $objective = new Objective): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Objective $objective = new Objective): bool
    {
        return false;
    }
}

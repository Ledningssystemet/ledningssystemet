<?php

namespace App\Policies;

use App\Models\Requirement;
use App\Models\User;

class RequirementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
                return $user->haveAnyAccessRights(['requirements.read', 'requirements.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Requirement $requirement = new Requirement): bool
    {
                return $user->haveAnyAccessRights(['requirements.read', 'requirements.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['requirements.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Requirement $requirement = new Requirement): bool
    {
                return $user->haveAnyAccessRights(['requirements.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Requirement $requirement = new Requirement): bool
    {
                return $user->can('update', $requirement);
    }
}

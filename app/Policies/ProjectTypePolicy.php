<?php

namespace App\Policies;

use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectTypePolicy
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
    public function view(User $user, ProjectType $riskProjectType = new ProjectType): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
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
    public function update(User $user, ProjectType $riskProjectType = new ProjectType): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProjectType $riskProjectType = new ProjectType): bool
    {
        if (null !== $riskProjectType->partner_id) {
            return false;
        }

        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProjectType $riskProjectType = new ProjectType): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProjectType $riskProjectType = new ProjectType): bool
    {
        return false;
    }
}

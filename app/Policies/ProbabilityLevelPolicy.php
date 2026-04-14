<?php

namespace App\Policies;

use App\Models\ProbabilityLevel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProbabilityLevelPolicy
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
    public function view(User $user, ProbabilityLevel $probabilityLevel = new ProbabilityLevel): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProbabilityLevel $probabilityLevel = new ProbabilityLevel): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProbabilityLevel $probabilityLevel = new ProbabilityLevel): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit'])) {
            return true;
        }

        return $user->can('update', $probabilityLevel);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProbabilityLevel $probabilityLevel = new ProbabilityLevel): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProbabilityLevel $probabilityLevel = new ProbabilityLevel): bool
    {
        return false;
    }
}

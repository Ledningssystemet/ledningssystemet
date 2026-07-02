<?php

namespace App\Policies;

use App\Models\ProbabilityLevel;
use App\Models\User;

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
        return $user->haveAnyAccessRights(['managementtools.edit']);
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
        return $user->can('update', $probabilityLevel);
    }
}

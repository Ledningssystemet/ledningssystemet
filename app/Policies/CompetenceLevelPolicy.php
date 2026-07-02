<?php

namespace App\Policies;

use App\Models\CompetenceLevel;
use App\Models\User;

class CompetenceLevelPolicy
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
    public function view(User $user, CompetenceLevel $competenceLevel = new CompetenceLevel): bool
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
    public function update(User $user, CompetenceLevel $competenceLevel = new CompetenceLevel): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CompetenceLevel $competenceLevel = new CompetenceLevel): bool
    {
        return $user->can('update', $competenceLevel);
    }
}

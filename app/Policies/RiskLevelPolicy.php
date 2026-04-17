<?php

namespace App\Policies;

use App\Models\RiskLevel;
use App\Models\User;

class RiskLevelPolicy
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
    public function view(User $user, RiskLevel $riskLevel = new RiskLevel): bool
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
    public function update(User $user, RiskLevel $riskLevel = new RiskLevel): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RiskLevel $riskLevel = new RiskLevel): bool
    {
        return $user->can('update', $riskLevel);
    }
}

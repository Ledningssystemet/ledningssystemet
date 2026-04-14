<?php

namespace App\Policies;

use App\Models\RiskLevelMapping;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RiskLevelMappingPolicy
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
    public function view(User $user, RiskLevelMapping $riskLevelMapping = new RiskLevelMapping): bool
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
    public function update(User $user, RiskLevelMapping $riskLevelMapping = new RiskLevelMapping): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RiskLevelMapping $riskLevelMapping = new RiskLevelMapping): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit'])) {
            return true;
        }

        return $user->can('update', $riskLevelMapping);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RiskLevelMapping $riskLevelMapping = new RiskLevelMapping): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RiskLevelMapping $riskLevelMapping = new RiskLevelMapping): bool
    {
        return false;
    }
}

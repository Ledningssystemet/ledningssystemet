<?php

namespace App\Policies;

use App\Models\ComplianceEvaluationRequirement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ComplianceEvaluationRequirementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['complianceevaluations.read', 'complianceevaluations.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ComplianceEvaluationRequirement $complianceEvaluationRequirement = new ComplianceEvaluationRequirement): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['complianceevaluations.read', 'complianceevaluations.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['complianceevaluations.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ComplianceEvaluationRequirement $complianceEvaluationRequirement = new ComplianceEvaluationRequirement): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['complianceevaluations.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ComplianceEvaluationRequirement $complianceEvaluationRequirement = new ComplianceEvaluationRequirement): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->can('update', $complianceEvaluationRequirement);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ComplianceEvaluationRequirement $complianceEvaluationRequirement = new ComplianceEvaluationRequirement): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ComplianceEvaluationRequirement $complianceEvaluationRequirement = new ComplianceEvaluationRequirement): bool
    {
        return false;
    }
}

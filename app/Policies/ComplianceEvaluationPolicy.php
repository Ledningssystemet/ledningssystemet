<?php

namespace App\Policies;

use App\Models\ComplianceEvaluation;
use App\Models\User;

class ComplianceEvaluationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->haveAnyAccessRights(['complianceevaluations.read', 'complianceevaluations.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ComplianceEvaluation $complianceEvaluation = new ComplianceEvaluation): bool
    {
        return $user->haveAnyAccessRights(['complianceevaluations.read', 'complianceevaluations.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['complianceevaluations.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ComplianceEvaluation $complianceEvaluation = new ComplianceEvaluation): bool
    {
        return $user->haveAnyAccessRights(['complianceevaluations.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ComplianceEvaluation $complianceEvaluation = new ComplianceEvaluation): bool
    {
        return $user->can('update', $complianceEvaluation);
    }
}

<?php

namespace App\Policies;

use App\Models\ComplianceEvaluationRequirement;
use App\Models\User;

class ComplianceEvaluationRequirementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return new ComplianceEvaluationPolicy()->viewAny($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ComplianceEvaluationRequirement $complianceEvaluationRequirement = new ComplianceEvaluationRequirement): bool
    {
        return  ($user->can('view', $complianceEvaluationRequirement->int_compliance_evaluation));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return new ComplianceEvaluationPolicy()->create($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ComplianceEvaluationRequirement $complianceEvaluationRequirement = new ComplianceEvaluationRequirement): bool
    {
        return  ($user->can('update', $complianceEvaluationRequirement->int_compliance_evaluation));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ComplianceEvaluationRequirement $complianceEvaluationRequirement = new ComplianceEvaluationRequirement): bool
    {
        return  ($user->can('delete', $complianceEvaluationRequirement->int_compliance_evaluation));
    }
}

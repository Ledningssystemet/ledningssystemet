<?php

namespace App\Policies;

use App\Models\ComplianceEvaluationRequirementSource;
use App\Models\User;

class ComplianceEvaluationRequirementSourcePolicy
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
    public function view(User $user, ComplianceEvaluationRequirementSource $complianceEvaluationRequirementSource = new ComplianceEvaluationRequirementSource): bool
    {
        return  ($user->can('view', $complianceEvaluationRequirementSource->int_compliance_evaluation));
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
    public function update(User $user, ComplianceEvaluationRequirementSource $complianceEvaluationRequirementSource = new ComplianceEvaluationRequirementSource): bool
    {
        return  ($user->can('update', $complianceEvaluationRequirementSource->int_compliance_evaluation));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ComplianceEvaluationRequirementSource $complianceEvaluationRequirementSource = new ComplianceEvaluationRequirementSource): bool
    {
        return  ($user->can('delete', $complianceEvaluationRequirementSource->int_compliance_evaluation));
    }
}

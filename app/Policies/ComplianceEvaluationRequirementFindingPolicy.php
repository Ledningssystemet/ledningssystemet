<?php

namespace App\Policies;

use App\Models\ComplianceEvaluationRequirementFinding;
use App\Models\User;

class ComplianceEvaluationRequirementFindingPolicy
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
    public function view(User $user, ComplianceEvaluationRequirementFinding $complianceEvaluationRequirementFinding = new ComplianceEvaluationRequirementFinding): bool
    {
        return  ($user->can('view', $complianceEvaluationRequirementFinding->int_compliance_evaluation_requirement));
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
    public function update(User $user, ComplianceEvaluationRequirementFinding $complianceEvaluationRequirementFinding = new ComplianceEvaluationRequirementFinding): bool
    {
        return  ($user->can('update', $complianceEvaluationRequirementFinding->int_compliance_evaluation_requirement));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ComplianceEvaluationRequirementFinding $complianceEvaluationRequirementFinding = new ComplianceEvaluationRequirementFinding): bool
    {
        return  ($user->can('delete', $complianceEvaluationRequirementFinding->int_compliance_evaluation_requirement));
    }
}

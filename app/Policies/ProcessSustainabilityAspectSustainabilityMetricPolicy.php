<?php

namespace App\Policies;

use App\Models\ProcessSustainabilityAspectSustainabilityMetric;
use App\Models\User;

class ProcessSustainabilityAspectSustainabilityMetricPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProcessSustainabilityAspectSustainabilityMetric $processSustainabilityAspectSustainabilityMetric = new ProcessSustainabilityAspectSustainabilityMetric): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProcessSustainabilityAspectSustainabilityMetric $processSustainabilityAspectSustainabilityMetric = new ProcessSustainabilityAspectSustainabilityMetric): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProcessSustainabilityAspectSustainabilityMetric $processSustainabilityAspectSustainabilityMetric = new ProcessSustainabilityAspectSustainabilityMetric): bool
    {
        return false;
    }
}

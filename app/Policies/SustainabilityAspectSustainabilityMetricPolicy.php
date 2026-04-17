<?php

namespace App\Policies;

use App\Models\SustainabilityAspectSustainabilityMetric;
use App\Models\User;

class SustainabilityAspectSustainabilityMetricPolicy
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
    public function view(User $user, SustainabilityAspectSustainabilityMetric $sustainabilityAspectSustainabilityMetric = new SustainabilityAspectSustainabilityMetric): bool
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
    public function update(User $user, SustainabilityAspectSustainabilityMetric $sustainabilityAspectSustainabilityMetric = new SustainabilityAspectSustainabilityMetric): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SustainabilityAspectSustainabilityMetric $sustainabilityAspectSustainabilityMetric = new SustainabilityAspectSustainabilityMetric): bool
    {
        return false;
    }
}

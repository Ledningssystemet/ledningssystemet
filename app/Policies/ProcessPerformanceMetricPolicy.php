<?php

namespace App\Policies;

use App\Models\ProcessPerformanceMetric;
use App\Models\User;

class ProcessPerformanceMetricPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
                return $user->haveAnyAccessRights(['processmetrics.read', 'processmetrics.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProcessPerformanceMetric $processPerformanceMetric = new ProcessPerformanceMetric): bool
    {
                return $user->haveAnyAccessRights(['processmetrics.read', 'processmetrics.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['processmetrics.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProcessPerformanceMetric $processPerformanceMetric = new ProcessPerformanceMetric): bool
    {
                return $user->haveAnyAccessRights(['processmetrics.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProcessPerformanceMetric $processPerformanceMetric = new ProcessPerformanceMetric): bool
    {
                return $user->can('update', $processPerformanceMetric);
    }
}

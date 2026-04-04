<?php

namespace App\Policies;

use App\Models\ActivityFlowTemplateItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ActivityFlowTemplateItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ActivityFlowTemplateItem $activityFlowTemplateItem = new ActivityFlowTemplateItem): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
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
    public function update(User $user, ActivityFlowTemplateItem $activityFlowTemplateItem = new ActivityFlowTemplateItem): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ActivityFlowTemplateItem $activityFlowTemplateItem = new ActivityFlowTemplateItem): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->can('update', $activityFlowTemplateItem);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ActivityFlowTemplateItem $activityFlowTemplateItem = new ActivityFlowTemplateItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ActivityFlowTemplateItem $activityFlowTemplateItem = new ActivityFlowTemplateItem): bool
    {
        return false;
    }
}

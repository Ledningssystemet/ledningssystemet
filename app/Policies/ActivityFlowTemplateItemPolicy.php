<?php

namespace App\Policies;

use App\Models\ActivityFlowTemplateItem;
use App\Models\User;

class ActivityFlowTemplateItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return new ActivityFlowTemplatePolicy()->update($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ActivityFlowTemplateItem $activityFlowTemplateItem = new ActivityFlowTemplateItem): bool
    {
        return  ($user->can('view', $activityFlowTemplateItem->int_activity_flow_template));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return new ActivityFlowTemplatePolicy()->create($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ActivityFlowTemplateItem $activityFlowTemplateItem = new ActivityFlowTemplateItem): bool
    {
        return  ($user->can('update', $activityFlowTemplateItem->int_activity_flow_template));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ActivityFlowTemplateItem $activityFlowTemplateItem = new ActivityFlowTemplateItem): bool
    {
        return $user->can('update', $activityFlowTemplateItem);
    }
}

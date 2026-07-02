<?php

namespace App\Policies;

use App\Models\AccessGroupUser;
use App\Models\User;

class AccessGroupUserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return new AccessGroupPolicy()->viewAny($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AccessGroupUser $accessGroupUser = new AccessGroupUser): bool
    {
        return  ($user->can('view', $accessGroupUser->int_access_group));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return new AccessGroupPolicy()->create($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AccessGroupUser $accessGroupUser = new AccessGroupUser): bool
    {
        return  ($user->can('update', $accessGroupUser->int_access_group));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AccessGroupUser $accessGroupUser = new AccessGroupUser): bool
    {
        return  ($user->can('delete', $accessGroupUser->int_access_group));
    }
}

<?php

namespace App\Policies;

use App\Models\Control;
use App\Models\User;

class ControlPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->haveAnyAccessRights(['controls.read', 'controls.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Control $control = new Control): bool
    {
        return $user->haveAnyAccessRights(['controls.read', 'controls.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['controls.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Control $control = new Control): bool
    {
        return $user->haveAnyAccessRights(['controls.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Control $control = new Control): bool
    {
        return $user->can('update', $control);
    }
}

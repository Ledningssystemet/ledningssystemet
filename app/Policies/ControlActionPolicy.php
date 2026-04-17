<?php

namespace App\Policies;

use App\Models\ControlAction;
use App\Models\User;

class ControlActionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return new ControlPolicy()->viewAny($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ControlAction $controlAction = new ControlAction): bool
    {
        return  ($user->can('view', $controlAction->int_control));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return new ControlPolicy()->create($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ControlAction $controlAction = new ControlAction): bool
    {
        return  ($user->can('update', $controlAction->int_control));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ControlAction $controlAction = new ControlAction): bool
    {
        return  ($user->can('delete', $controlAction->int_control));
    }
}

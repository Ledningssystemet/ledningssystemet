<?php

namespace App\Policies;

use App\Models\ControlActionMapping;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ControlActionMappingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return new ControlActionPolicy()->viewAny($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ControlActionMapping $controlActionMapping = new ControlActionMapping): bool
    {
        return $controlActionMapping->int_control_action()->exists() && (new ControlActionPolicy())->view($user, $controlActionMapping->int_control_action);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return new ControlActionPolicy()->create($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ControlActionMapping $controlActionMapping = new ControlActionMapping): bool
    {
        return $controlActionMapping->int_control_action()->exists() && (new ControlActionPolicy())->update($user, $controlActionMapping->int_control_action);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ControlActionMapping $controlActionMapping = new ControlActionMapping): bool
    {
        return $controlActionMapping->int_control_action()->exists() && (new ControlActionPolicy())->delete($user, $controlActionMapping->int_control_action);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ControlActionMapping $controlActionMapping = new ControlActionMapping): bool
    {
        return $controlActionMapping->int_control_action()->exists() && (new ControlActionPolicy())->restore($user, $controlActionMapping->int_control_action);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ControlActionMapping $controlActionMapping = new ControlActionMapping): bool
    {
        return $controlActionMapping->int_control_action()->exists() && (new ControlActionPolicy())->forceDelete($user, $controlActionMapping->int_control_action);
    }
}

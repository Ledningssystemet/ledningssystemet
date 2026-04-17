<?php

namespace App\Policies;

use App\Models\SustainabilityAspect;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SustainabilityAspectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SustainabilityAspect $sustainabilityAspect = new SustainabilityAspect): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SustainabilityAspect $sustainabilityAspect = new SustainabilityAspect): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SustainabilityAspect $sustainabilityAspect = new SustainabilityAspect): bool
    {
        return $user->can('update', $sustainabilityAspect);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SustainabilityAspect $sustainabilityAspect = new SustainabilityAspect): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SustainabilityAspect $sustainabilityAspect = new SustainabilityAspect): bool
    {
        return false;
    }
}

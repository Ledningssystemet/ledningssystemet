<?php

namespace App\Policies;

use App\Models\AvailabilityClass;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AvailabilityClassPolicy
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
    public function view(User $user, AvailabilityClass $availabilityClass = new AvailabilityClass): bool
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
    public function update(User $user, AvailabilityClass $availabilityClass = new AvailabilityClass): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AvailabilityClass $availabilityClass = new AvailabilityClass): bool
    {
                return $user->can('update', $availabilityClass);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AvailabilityClass $availabilityClass = new AvailabilityClass): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AvailabilityClass $availabilityClass = new AvailabilityClass): bool
    {
        return false;
    }
}

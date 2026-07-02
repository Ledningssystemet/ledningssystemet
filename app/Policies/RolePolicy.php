<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return (!config('ledningssystemet.disable_staff', false)) && $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role = new Role): bool
    {
        return (!config('ledningssystemet.disable_staff', false)) && $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return (!config('ledningssystemet.disable_staff', false)) && $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Role $role = new Role): bool
    {
        return (!config('ledningssystemet.disable_staff', false)) && $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Role $role = new Role): bool
    {
                return $user->can('update', $role);
    }
}

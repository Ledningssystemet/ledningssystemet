<?php

namespace App\Policies;

use App\Models\InformationType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InformationTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['processes.read', 'processes.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InformationType $informationType = new InformationType): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['processes.read', 'processes.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['processes.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InformationType $informationType = new InformationType): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['processes.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InformationType $informationType = new InformationType): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return ($user->can('update', $informationType) && !count($informationType->int_processes()));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InformationType $informationType = new InformationType): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InformationType $informationType = new InformationType): bool
    {
        return false;
    }
}

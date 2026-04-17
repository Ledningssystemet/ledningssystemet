<?php

namespace App\Policies;

use App\Models\InformationType;
use App\Models\User;

class InformationTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->haveAnyAccessRights(['processes.read', 'processes.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InformationType $informationType = new InformationType): bool
    {
        return $user->haveAnyAccessRights(['processes.read', 'processes.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['processes.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InformationType $informationType = new InformationType): bool
    {
        return $user->haveAnyAccessRights(['processes.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InformationType $informationType = new InformationType): bool
    {
        return ($user->can('update', $informationType) && !$informationType->int_processes()->count());
    }
}

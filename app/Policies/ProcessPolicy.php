<?php

namespace App\Policies;

use App\Models\Process;
use App\Models\User;

class ProcessPolicy
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
    public function view(User $user, Process $process = new Process): bool
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
    public function update(User $user, Process $process = new Process): bool
    {
                return $user->haveAnyAccessRights(['processes.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Process $process = new Process): bool
    {
                return $user->can('update', $process);
    }
}

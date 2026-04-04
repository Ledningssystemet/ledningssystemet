<?php

namespace App\Policies;

use App\Models\ProcessActivity;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProcessActivityPolicy
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
    public function view(User $user, ProcessActivity $processActivity = new ProcessActivity): bool
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
    public function update(User $user, ProcessActivity $processActivity = new ProcessActivity): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['processes.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProcessActivity $processActivity = new ProcessActivity): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->can('update', $processActivity);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProcessActivity $processActivity = new ProcessActivity): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProcessActivity $processActivity = new ProcessActivity): bool
    {
        return false;
    }
}

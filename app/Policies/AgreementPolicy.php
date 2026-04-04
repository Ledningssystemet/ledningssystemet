<?php

namespace App\Policies;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AgreementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['agreements.read', 'agreements.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Agreement $agreement = new Agreement): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['agreements.read', 'agreements.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['agreements.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Agreement $agreement = new Agreement): bool
    {
        return $user->haveAnyAccessRights(['agreements.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Agreement $agreement = new Agreement): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->can('update', $agreement);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Agreement $agreement = new Agreement): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Agreement $agreement = new Agreement): bool
    {
        return false;
    }
}

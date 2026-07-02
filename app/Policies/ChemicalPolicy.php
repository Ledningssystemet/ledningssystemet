<?php

namespace App\Policies;

use App\Models\Chemical;
use App\Models\User;

class ChemicalPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->haveAnyAccessRights(['chemicalregister.read', 'chemicalregister.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Chemical $chemical = new Chemical): bool
    {
        return $user->haveAnyAccessRights(['chemicalregister.read', 'chemicalregister.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['chemicalregister.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Chemical $chemical = new Chemical): bool
    {
        return $user->haveAnyAccessRights(['chemicalregister.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Chemical $chemical = new Chemical): bool
    {
        return $user->can('update', $chemical);
    }
}

<?php

namespace App\Policies;

use App\Models\LegalBasis;
use App\Models\User;

class LegalBasisPolicy
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
    public function view(User $user, LegalBasis $legalBasis = new LegalBasis): bool
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
    public function update(User $user, LegalBasis $legalBasis = new LegalBasis): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LegalBasis $legalBasis = new LegalBasis): bool
    {
        return $user->can('update', $legalBasis);
    }
}

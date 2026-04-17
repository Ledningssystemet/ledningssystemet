<?php

namespace App\Policies;

use App\Models\LegalBasisProcess;
use App\Models\User;

class LegalBasisProcessPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LegalBasisProcess $legalBasisProcess = new LegalBasisProcess): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LegalBasisProcess $legalBasisProcess = new LegalBasisProcess): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LegalBasisProcess $legalBasisProcess = new LegalBasisProcess): bool
    {
        return false;
    }
}

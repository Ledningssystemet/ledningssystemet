<?php

namespace App\Policies;

use App\Models\ConfidentialityClass;
use App\Models\User;

class ConfidentialityClassPolicy
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
    public function view(User $user, ConfidentialityClass $confidentialityClass = new ConfidentialityClass): bool
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
    public function update(User $user, ConfidentialityClass $confidentialityClass = new ConfidentialityClass): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ConfidentialityClass $confidentialityClass = new ConfidentialityClass): bool
    {
        return $user->can('update', $confidentialityClass);
    }
}

<?php

namespace App\Policies;

use App\Models\SystemConfiguration;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SystemConfigurationPolicy
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
    public function view(User $user, SystemConfiguration $systemConfiguration): bool
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
    public function update(User $user, SystemConfiguration $systemConfiguration): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SystemConfiguration $systemConfiguration): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SystemConfiguration $systemConfiguration): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SystemConfiguration $systemConfiguration): bool
    {
        return false;
    }
}

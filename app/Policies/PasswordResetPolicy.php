<?php

namespace App\Policies;

use App\Models\PasswordReset;
use App\Models\User;

class PasswordResetPolicy
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
    public function view(User $user, PasswordReset $passwordReset = new PasswordReset): bool
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
    public function update(User $user, PasswordReset $passwordReset = new PasswordReset): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PasswordReset $passwordReset = new PasswordReset): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\PendingActivity;
use App\Models\User;

class PendingActivityPolicy
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
    public function view(User $user, PendingActivity $pendingActivity = new PendingActivity): bool
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
    public function update(User $user, PendingActivity $pendingActivity = new PendingActivity): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PendingActivity $pendingActivity = new PendingActivity): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\AIQuery;
use App\Models\User;

class AIQueryPolicy
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
    public function view(User $user, AIQuery $aIQuery = new AIQuery): bool
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
    public function update(User $user, AIQuery $aIQuery = new AIQuery): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AIQuery $aIQuery = new AIQuery): bool
    {
        return false;
    }
}

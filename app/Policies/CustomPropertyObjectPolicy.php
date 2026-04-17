<?php

namespace App\Policies;

use App\Models\CustomPropertyObject;
use App\Models\User;

class CustomPropertyObjectPolicy
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
    public function view(User $user, CustomPropertyObject $customPropertyObject = new CustomPropertyObject): bool
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
    public function update(User $user, CustomPropertyObject $customPropertyObject = new CustomPropertyObject): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomPropertyObject $customPropertyObject = new CustomPropertyObject): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\ObjectHistory;
use App\Models\User;

class ObjectHistoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->haveAnyAccessRights(['systemadministrator.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ObjectHistory $objectHistory = new ObjectHistory): bool
    {
        return $user->haveAnyAccessRights(['systemadministrator.edit']);
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
    public function update(User $user, ObjectHistory $objectHistory = new ObjectHistory): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ObjectHistory $objectHistory = new ObjectHistory): bool
    {
        return false;
    }
}

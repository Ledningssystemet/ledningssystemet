<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
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
    public function view(User $user, Department $department = new Department): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['systemadministrator.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Department $department = new Department): bool
    {
        return $user->haveAnyAccessRights(['systemadministrator.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Department $department = new Department): bool
    {
        return $user->can('update', $department);
    }
}

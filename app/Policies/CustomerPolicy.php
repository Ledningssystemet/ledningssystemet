<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->haveAnyAccessRights(['customers.read', 'customers.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Customer $customer = new Customer): bool
    {
        return $user->haveAnyAccessRights(['customers.read', 'customers.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['customers.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Customer $customer = new Customer): bool
    {
        return $user->haveAnyAccessRights(['customers.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Customer $customer = new Customer): bool
    {
        return $user->can('update', $customer);
    }
}

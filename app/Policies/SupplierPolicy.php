<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SupplierPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (config('ledningssystemet.disable_supplier', false))
            return false;

        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['suppliers.read', 'suppliers.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Supplier $supplier = new Supplier): bool
    {
        if (config('ledningssystemet.disable_supplier', false))
            return false;

        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->haveAnyAccessRights(['suppliers.read', 'suppliers.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return (!config('ledningssystemet.disable_supplier', false)) && $user->haveAnyAccessRights(['suppliers.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Supplier $supplier = new Supplier): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return (!config('ledningssystemet.disable_supplier', false)) && $user->haveAnyAccessRights(['suppliers.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Supplier $supplier = new Supplier): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->can('update', $supplier);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Supplier $supplier = new Supplier): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Supplier $supplier = new Supplier): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\SupplierRequirement;
use App\Models\User;

class SupplierRequirementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (config('ledningssystemet.disable_supplier', false)) {
            return false;
        }

        return $user->haveAnyAccessRights(['managementtools.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SupplierRequirement $supplierRequirement = new SupplierRequirement): bool
    {
        if (config('ledningssystemet.disable_supplier', false)) {
            return false;
        }

        return $user->haveAnyAccessRights(['managementtools.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (config('ledningssystemet.disable_supplier', false)) {
            return false;
        }

        return $user->haveAnyAccessRights(['managementtools.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SupplierRequirement $supplierRequirement = new SupplierRequirement): bool
    {
        if (config('ledningssystemet.disable_supplier', false)) {
            return false;
        }

        return $user->haveAnyAccessRights(['managementtools.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SupplierRequirement $supplierRequirement = new SupplierRequirement): bool
    {
        if (config('ledningssystemet.disable_supplier', false)) {
            return false;
        }

        return $user->haveAnyAccessRights(['managementtools.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SupplierRequirement $supplierRequirement = new SupplierRequirement): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SupplierRequirement $supplierRequirement = new SupplierRequirement): bool
    {
        return false;
    }
}

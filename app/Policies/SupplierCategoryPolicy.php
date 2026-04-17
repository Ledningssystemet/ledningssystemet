<?php

namespace App\Policies;

use App\Models\SupplierCategory;
use App\Models\User;

class SupplierCategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (config('ledningssystemet.disable_supplier', false)) {
            return false;
        }

        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SupplierCategory $supplierCategory = new SupplierCategory): bool
    {
        if (config('ledningssystemet.disable_supplier', false)) {
            return false;
        }

        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (config('ledningssystemet.disable_supplier', false)) {
            return false;
        }

        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SupplierCategory $supplierCategory = new SupplierCategory): bool
    {
        if (config('ledningssystemet.disable_supplier', false)) {
            return false;
        }

        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SupplierCategory $supplierCategory = new SupplierCategory): bool
    {
        if (config('ledningssystemet.disable_supplier', false)) {
            return false;
        }

        return $user->haveAnyAccessRights(['managementtools.edit']);
    }
}

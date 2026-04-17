<?php

namespace App\Policies;

use App\Models\ProcessActivitySupplier;
use App\Models\User;

class ProcessActivitySupplierPolicy
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
    public function view(User $user, ProcessActivitySupplier $processActivitySupplier = new ProcessActivitySupplier): bool
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
    public function update(User $user, ProcessActivitySupplier $processActivitySupplier = new ProcessActivitySupplier): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProcessActivitySupplier $processActivitySupplier = new ProcessActivitySupplier): bool
    {
        return false;
    }
}

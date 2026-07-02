<?php

namespace App\Policies;

use App\Models\Finding;
use App\Models\User;

class FindingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return (!config('ledningssystemet.disable_finding', false)) && $user->haveAnyAccessRights(['findings.read', 'findings.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Finding $finding = new Finding): bool
    {
        return (!config('ledningssystemet.disable_finding', false)) && $user->haveAnyAccessRights(['findings.read', 'findings.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return (!config('ledningssystemet.disable_finding', false));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Finding $finding = new Finding): bool
    {
        return (!config('ledningssystemet.disable_finding', false)) && $user->haveAnyAccessRights(['findings.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Finding $finding = new Finding): bool
    {
        return $user->can('update', $finding);
    }
}

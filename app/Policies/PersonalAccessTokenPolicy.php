<?php

namespace App\Policies;

use App\Models\PersonalAccessToken;
use App\Models\User;

class PersonalAccessTokenPolicy
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
    public function view(User $user, PersonalAccessToken $personalAccessToken = new PersonalAccessToken): bool
    {
        return $user->haveAnyAccessRights(['systemadministrator.edit']);
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
    public function update(User $user, PersonalAccessToken $personalAccessToken = new PersonalAccessToken): bool
    {
        return $user->haveAnyAccessRights(['systemadministrator.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PersonalAccessToken $personalAccessToken = new PersonalAccessToken): bool
    {
        return $user->haveAnyAccessRights(['systemadministrator.edit']);
    }
}

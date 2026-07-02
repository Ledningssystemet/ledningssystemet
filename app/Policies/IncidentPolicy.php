<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->haveAnyAccessRights(['incidents.read', 'incidents.edit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Incident $incident = new Incident): bool
    {
        return $user->haveAnyAccessRights(['incidents.read', 'incidents.edit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['incidents.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Incident $incident = new Incident): bool
    {
        return $user->haveAnyAccessRights(['incidents.edit']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Incident $incident = new Incident): bool
    {
        return $user->can('update', $incident);
    }
}

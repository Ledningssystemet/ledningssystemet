<?php

namespace App\Policies;

use App\Models\IncidentLog;
use App\Models\User;

class IncidentLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return new IncidentPolicy()->viewAny($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, IncidentLog $incidentLog = new IncidentLog): bool
    {
        return  ($user->can('view', $incidentLog->int_incident));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return new IncidentPolicy()->create($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IncidentLog $incidentLog = new IncidentLog): bool
    {
        return  ($user->can('update', $incidentLog->int_incident));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IncidentLog $incidentLog = new IncidentLog): bool
    {
        return $user->can('update', $incidentLog);
    }
}

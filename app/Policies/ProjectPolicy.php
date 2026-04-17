<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if($user->haveAnyAccessRights(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']))
            return true;

        if(\App\Models\Project::where('responsible_user_id', $user->id)->exists())
            return true;

        if(\App\Models\Project::whereHas('int_users', function($q) use ($user) {
            $q->where('users.id', $user->id);
        })->exists())
            return true;

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $riskProject = new Project): bool
    {
        if ($user->haveAnyAccessRights(['riskadministrator.edit']))
            return true;

        if ($riskProject->responsible_user_id == $user->id)
            return true;

        if (false !== array_search($user->id, $riskProject->int_users()->pluck('users.id')->all()))
            return true;

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $riskProject = new Project): bool
    {
                if ($user->haveAnyAccessRights(['riskadministrator.edit']))
            return true;

        if ($riskProject->responsible_user_id == $user->id)
            return true;

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $riskProject = new Project): bool
    {
                return $user->can('update', $riskProject);
    }
}

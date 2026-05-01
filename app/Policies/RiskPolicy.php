<?php

namespace App\Policies;

use App\Models\Risk;
use App\Models\User;

class RiskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->haveAnyAccessRights(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']))
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
    public function view(User $user, Risk $risk = new Risk): bool
    {
        if (null == $risk->project_id) {
            if ($user->haveAnyAccessRights(['riskadministrator.edit', 'riskall.edit']))
                return true;

            if ((null != $risk->riskowner_id) && ($risk->riskowner_id == $user->id))
                return true;

            if ($user->haveAnyAccessRights(['riskdepartment.edit']) && ($user->int_departments()->where('departments.id', $risk->department_id)->exists()))
                return true;
        } else {
            // Risk project risk
            if ($user->haveAnyAccessRights(['riskadministrator.edit']))
                return true;

            if ($risk->int_project->responsible_user_id == $user->id)
                return true;

            if (false !== array_search($user->id, $risk->int_project->int_users()->pluck('users.id')->all()))
                return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Risk $risk = new Risk): bool
    {
                if (null == $risk->project_id) {
            if ($user->haveAnyAccessRights(['riskadministrator.edit', 'riskall.edit']))
                return true;

            if ((null != $risk->riskowner_id) && ($risk->riskowner_id == $user->id))
                return true;

            if ($user->haveAnyAccessRights(['riskdepartment.edit']) && ($user->int_departments()->where('departments.id', $risk->department_id)->exists()))
                return true;
        } else {
            // Risk project risk
            if ($user->haveAnyAccessRights(['riskadministrator.edit']))
                return true;

            if ($risk->int_project->responsible_user_id == $user->id)
                return true;

            if (false !== array_search($user->id, $risk->int_project->int_users()->pluck('users.id')->all()))
                return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Risk $risk = new Risk): bool
    {
        if ($user->haveAnyAccessRights(['riskadministrator.edit']))
            return true;


        return $user->can('update', $risk);
    }
}

<?php

namespace App\Policies;

use App\Models\RiskProject;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RiskProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if($user->haveAnyAccessRights(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']))
            return true;

        if(\App\Models\RiskProject::where('responsible_user_id', $user->id)->exists())
            return true;

        if(\App\Models\RiskProject::whereHas('int_users', function($q) use ($user) {
            $q->where('users.id', $user->id);
        })->exists())
            return true;

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RiskProject $riskProject = new RiskProject): bool
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
        return $user->haveAnyAccessRights(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RiskProject $riskProject = new RiskProject): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        if ($user->haveAnyAccessRights(['riskadministrator.edit']))
            return true;

        if ($riskProject->responsible_user_id == $user->id)
            return true;

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RiskProject $riskProject = new RiskProject): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return $user->can('update', $riskProject);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RiskProject $riskProject = new RiskProject): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RiskProject $riskProject = new RiskProject): bool
    {
        return false;
    }
}

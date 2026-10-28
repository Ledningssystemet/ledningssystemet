<?php

namespace Ledningssystemet\Ledningssystemet\Policies;

use Illuminate\Auth\Access\Response;
use Ledningssystemet\Ledningssystemet\Models\Project;
use Ledningssystemet\Ledningssystemet\Models\ProjectActivity;
use Ledningssystemet\Ledningssystemet\Models\RecurringProjectActivity;
use Ledningssystemet\Ledningssystemet\Models\User;

class ProjectActivityPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
       return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProjectActivity|RecurringProjectActivity $projectActivity): bool
    {
       return ($projectActivity->responsible_user_id === $user->id) ||
          ($projectActivity->int_project->int_users->contains($user)) ||
          ($projectActivity->int_project->responsible_user_id === $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
       // Ensure that the user has access to the project and can write to it
         if (!request()->has('project_id'))
            return false;

         $project = Project::find(request()->input('project_id'));
         if(!$project)
            return false;

         return $project->canWrite($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProjectActivity|RecurringProjectActivity $projectActivity): bool
    {
         return $projectActivity->int_project->canWrite($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProjectActivity|RecurringProjectActivity $projectActivity): bool
    {
        return $projectActivity->int_project->canWrite($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProjectActivity|RecurringProjectActivity $projectActivity): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProjectActivity|RecurringProjectActivity $projectActivity): bool
    {
        return $projectActivity->int_project->canWrite($user);
    }
}

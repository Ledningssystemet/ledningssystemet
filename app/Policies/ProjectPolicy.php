<?php

namespace Ledningssystemet\Ledningssystemet\Policies;

use Illuminate\Auth\Access\Response;
use Ledningssystemet\Ledningssystemet\Models\Project;
use Ledningssystemet\Ledningssystemet\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // The Model handles the filtration internally
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): bool
    {
       if ($project->responsible_user_id == $user->id)
          return true;

       return $project->int_users()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
       return $user->hasPermissionTo('projects.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
       return $project->canWrite($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
       // Allow delete if the user is responsible and the project and the project is archived
       return ($project->responsible_user_id == $user->id) && $project->archived_at;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return $this->delete($user, $project);
    }
}

<?php

namespace Ledningssystemet\Ledningssystemet\Policies;

use Illuminate\Database\Eloquent\Model;
use Ledningssystemet\Ledningssystemet\Models\Project;
use Ledningssystemet\Ledningssystemet\Models\ProjectMeetingMention;
use Ledningssystemet\Ledningssystemet\Models\User;

class ProjectItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $project = $this->requestedProject();

        return $project !== null
            && ($project->responsible_user_id === $user->id
                || $project->int_users()->where('users.id', $user->id)->exists());
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Model $projectItem): bool
    {
        $project = $this->projectFor($projectItem);

        return $project !== null
            && ($project->responsible_user_id === $user->id
                || $project->int_users()->where('users.id', $user->id)->exists());
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->requestedProject()?->canWrite($user) ?? false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $projectItem): bool
    {
        return $this->projectFor($projectItem)?->canWrite($user) ?? false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $projectItem): bool
    {
        return $this->projectFor($projectItem)?->canWrite($user) ?? false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Model $projectItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Model $projectItem): bool
    {
        return $this->projectFor($projectItem)?->canWrite($user) ?? false;
    }

    private function requestedProject(): ?Project
    {
        $projectId = request()->input('filter.project_id', request()->input('project_id'));

        return $projectId ? Project::find($projectId) : null;
    }

    private function projectFor(Model $projectItem): ?Project
    {
        if ($projectItem instanceof ProjectMeetingMention) {
            return $projectItem->int_meeting?->int_project;
        }

        return method_exists($projectItem, 'int_project') ? $projectItem->int_project : null;
    }
}

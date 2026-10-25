<?php

namespace Ledningssystemet\Ledningssystemet\Policies;

use Ledningssystemet\Ledningssystemet\Models\ProjectMeetingMention;
use Ledningssystemet\Ledningssystemet\Models\User;

class ProjectMeetingMentionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProjectMeetingMention $mention): bool
    {
        return $mention->user_id === $user->id && $user->can('view', $mention->int_meeting);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProjectMeetingMention $mention): bool
    {
        return false;
    }

    public function delete(User $user, ProjectMeetingMention $mention): bool
    {
        return false;
    }

    public function restore(User $user, ProjectMeetingMention $mention): bool
    {
        return false;
    }

    public function forceDelete(User $user, ProjectMeetingMention $mention): bool
    {
        return false;
    }
}

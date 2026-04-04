<?php

namespace App\Policies;

use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentVersionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DocumentVersion $documentVersion = new DocumentVersion): bool
    {
        return (($user->id == $documentVersion->approver_id) ||
                ($user->id == $documentVersion->int_library_document->responsible_user_id) ||
                $user->haveAnyAccessRights(['managementtools.edit']));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DocumentVersion $documentVersion = new DocumentVersion): bool
    {
        return ($user->haveAnyAccessRights(['managementtools.edit']) ||
                ($documentVersion->int_library_document->responsible_user_id == auth()->user()->id));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DocumentVersion $documentVersion = new DocumentVersion): bool
    {
        return !$documentVersion->approved_at && $user->can('update', $documentVersion);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DocumentVersion $documentVersion = new DocumentVersion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DocumentVersion $documentVersion = new DocumentVersion): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\LibraryDocument;
use App\Models\User;

class LibraryDocumentPolicy
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
    public function view(User $user, LibraryDocument $libraryDocument = new LibraryDocument): bool
    {
        return ($user->haveAnyAccessRights(['managementtools.edit']) ||
            ($user->id == $libraryDocument->responsible_user_id));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LibraryDocument $libraryDocument = new LibraryDocument): bool
    {
        return ($user->haveAnyAccessRights(['managementtools.edit']) ||
        ($user->id == $libraryDocument->responsible_user_id));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LibraryDocument $libraryDocument = new LibraryDocument): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit']);
    }
}

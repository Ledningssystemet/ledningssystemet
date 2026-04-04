<?php

namespace App\Policies;

use App\Models\LibraryDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LibraryDocument $libraryDocument = new LibraryDocument): bool
    {
        if ($user->haveAnyAccessRights(['superadmin.edit']))
            return true;

        return ($user->haveAnyAccessRights(['managementtools.edit']) ||
                ($user->id == $libraryDocument->responsible_user_id));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LibraryDocument $libraryDocument = new LibraryDocument): bool
    {
        return $user->haveAnyAccessRights(['managementtools.edit', 'superadmin.edit']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LibraryDocument $libraryDocument = new LibraryDocument): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LibraryDocument $libraryDocument = new LibraryDocument): bool
    {
        return false;
    }
}

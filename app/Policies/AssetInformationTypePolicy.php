<?php

namespace App\Policies;

use App\Models\AssetInformationType;
use App\Models\User;

class AssetInformationTypePolicy
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
    public function view(User $user, AssetInformationType $assetInformationType = new AssetInformationType): bool
    {
        return false;
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
    public function update(User $user, AssetInformationType $assetInformationType = new AssetInformationType): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssetInformationType $assetInformationType = new AssetInformationType): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\AssetAssetDependancy;
use App\Models\User;

class AssetAssetDependancyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return  ($user->can('viewAny', Asset::class));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssetAssetDependancy $assetAssetDependancy = new AssetAssetDependancy): bool
    {
        return  ($user->can('view', $assetAssetDependancy->int_depending_asset));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return  ($user->can('create', Asset::class));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssetAssetDependancy $assetAssetDependancy = new AssetAssetDependancy): bool
    {
        return  ($user->can('update', $assetAssetDependancy->int_depending_asset));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssetAssetDependancy $assetAssetDependancy = new AssetAssetDependancy): bool
    {
        return  ($user->can('delete', $assetAssetDependancy->int_depending_asset));
    }
}

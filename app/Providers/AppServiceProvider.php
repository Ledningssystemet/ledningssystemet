<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Tell Sanctum to use the project's own PersonalAccessToken model
        // so that extra relations and helpers are available on token instances.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        /* Register event listener for cache flush on database modification, except for session update. */
        DB::listen(function (QueryExecuted $query) {
            if (  (0 !== stripos($query->sql, 'select ')) &&
                  (false === stripos($query->sql, '`sessions`'))
                ) {
                Cache::flush();
            }
        });

        /*
         * Authorization is now handled by policies in app/Policies/
         *
         * The following gate-based authorization has been migrated to proper Laravel Policies:
         * - index/viewAny: Check for read access
         * - view: Check for read access to specific model
         * - create: Check for write access
         * - update: Check for write access with responsibility checks
         * - delete: Check for admin/write access with additional restrictions
         *
         * Policies are automatically discovered and used when calling:
         * - $user->can('view', $model)
         * - $this->authorize('update', $model)
         * - Gate::authorize('delete', $model)
         *
         * See MIGRATION_SUMMARY.md for detailed documentation of all policies.
         */
    }
}

<?php

namespace Ledningssystemet\Ledningssystemet\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Broadcast::routes();

        // Use a path relative to this package rather than base_path(), since
        // when this package is required as a Composer dependency (e.g. by
        // the proprietary customer application), base_path() resolves to the
        // *host* application's root, which does not have this file.
        require __DIR__ . '/../../routes/channels.php';
    }
}

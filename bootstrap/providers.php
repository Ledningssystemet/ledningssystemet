<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\PluginServiceProvider;

return [
    PluginServiceProvider::class,
    AppServiceProvider::class,
    EventServiceProvider::class,
];

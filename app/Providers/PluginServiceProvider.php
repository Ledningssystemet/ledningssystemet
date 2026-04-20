<?php

namespace App\Providers;

use App\Plugins\PluginHookRegistry;
use App\Plugins\PluginRuntime;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginHookRegistry::class, fn (): PluginHookRegistry => new PluginHookRegistry());

        $this->app->singleton(PluginRuntime::class, function (Application $app): PluginRuntime {
            return new PluginRuntime(
                $app,
                $app->make(PluginHookRegistry::class),
                (array) $app['config']->get('plugins', []),
            );
        });

        $this->app->make(PluginRuntime::class)->registerBackendPlugins();
    }

    public function boot(PluginRuntime $pluginRuntime): void
    {
        $pluginRuntime->bootBackendPlugins();

        View::composer('app', function ($view) use ($pluginRuntime): void {
            $view->with('pluginImportMap', $pluginRuntime->frontendImportMap());
            $view->with('pluginRuntimeConfig', $pluginRuntime->frontendRuntimeConfig(request()));
        });
    }
}


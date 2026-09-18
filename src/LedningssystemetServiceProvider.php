<?php

namespace Ledningssystemet\Ledningssystemet;

use App\Console\Commands\graphsync;
use App\Console\Commands\riskreassess;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\ServiceProvider;
use Ledningssystemet\Ledningssystemet\Providers\AppServiceProvider;
use Ledningssystemet\Ledningssystemet\Providers\AuthServiceProvider;
use Ledningssystemet\Ledningssystemet\Providers\EventServiceProvider;
use Ledningssystemet\Ledningssystemet\Providers\FortifyServiceProvider;
use Ledningssystemet\Ledningssystemet\Providers\RouteServiceProvider;
use Symfony\Component\Finder\Finder;

class LedningssystemetServiceProvider extends ServiceProvider
{
   public function boot(): void
   {
      $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

      // Register the package views both under the 'ledningssystemet::' namespace
      // (used for override/publish support) and as a plain view location, since
      // the package's own controllers/routes reference views without a namespace
      // (e.g. view('dashboard.dashboard')). Because this is appended after the
      // host application's own resources/views path, host apps (like the
      // proprietary customer application) can transparently override any view
      // by placing a file with the same relative path in their own
      // resources/views directory.
      $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ledningssystemet');
      $this->app['view']->addLocation(__DIR__ . '/../resources/views');

      // Route registration (including the "web"/"api" middleware groups and
      // the "/api" prefix) is handled by Providers\RouteServiceProvider,
      // registered below - it owns routes/web.php and routes/api.php
      // entirely so they're only ever wired up in one, correct, place.

      // Register the package's translations. Almost all `__('...')` calls in
      // this codebase use plain English sentences (looked up in the JSON
      // translation files), so addJsonPath() is what makes those resolve.
      // The host application's own JSON translations (if any exist at
      // lang_path("{locale}.json")) are merged in *after* this package's,
      // so a host-defined string always wins over the package's for the
      // same key. addPath() registers the PHP group files (auth.php,
      // pagination.php, passwords.php, validation.php) that Laravel's own
      // validator/auth error messages rely on. loadTranslationsFrom() also
      // registers a 'ledningssystemet::' namespace, so it can be published
      // and overridden the standard Laravel package way if ever needed.
      $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');
      $this->app['translator']->addPath(__DIR__ . '/../lang');
      $this->loadTranslationsFrom(__DIR__ . '/../lang', 'ledningssystemet');

      if ($this->app->runningInConsole()) {
         $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/ledningssystemet'),
         ], 'ledningssystemet-views');
         $this->publishes(
            collect($this->configFiles())
               ->mapWithKeys(fn (string $name) => [
                  __DIR__ . "/../config/{$name}.php" => config_path("{$name}.php"),
               ])
               ->all(),
            'ledningssystemet-config'
         );

         // Static public assets (third-party JS/CSS vendors, images, favicon)
         // that ship with the package but must live in the host application's
         // public/ directory to be served. Published under the standard
         // 'laravel-assets' tag so `composer install`/`composer update` (which
         // already runs `artisan vendor:publish --tag=laravel-assets --force`)
         // keeps them up to date automatically.
         $this->publishes([
            __DIR__ . '/../public/vendors' => public_path('vendors'),
            __DIR__ . '/../public/images' => public_path('images'),
            __DIR__ . '/../public/favicon.png' => public_path('favicon.png'),
         ], ['ledningssystemet-assets', 'laravel-assets']);

         // Host applications (like the proprietary customer application) use the
         // Laravel 11+ bootstrap style, which never instantiates this package's
         // own (legacy) App\Console\Kernel. That means its commands() and
         // schedule() methods are never called when the package is required via
         // Composer. Register the artisan commands and the schedule explicitly
         // here instead, so they work both standalone and as a dependency.
         $this->commands([
            graphsync::class,
            riskreassess::class,
         ]);

         $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);

            // Graph sync
            $schedule->command('ledningssystemet:graphsync')->hourly()->withoutOverlapping();

            // Perform risk re-assessment
            $schedule->command('ledningssystemet:risksreassess')->dailyAt('05:00');
         });
      }
   }

   public function register(): void
   {
      // Host applications (like the proprietary customer application) may
      // ship with no config/*.php files of their own at all, relying on
      // this package for behaviour. Laravel's LoadConfiguration bootstrapper
      // fills any config key that isn't defined by the host app with the
      // *framework's own generic defaults* (vendor/laravel/framework/config)
      // before any service provider ever runs. That means the usual
      // mergeConfigFrom() (which always lets the already-loaded config win)
      // can never actually apply this package's tailored defaults on top of
      // the framework's generic ones.
      //
      // Instead, take full ownership of every config key this package ships
      // a config file for, unless the host application has published/added
      // its own config file with the same name (config_path("{$name}.php")
      // exists) - in that case the host's file already took precedence
      // during Laravel's normal config loading and must not be touched.
      if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
         return;
      }

      $config = $this->app->make('config');

      foreach ($this->configFiles() as $name) {
         if (! is_file(config_path("{$name}.php"))) {
            $config->set($name, require __DIR__ . "/../config/{$name}.php");
         }
      }

      // This package was originally built as a standalone Laravel app, where
      // these providers get wired up via its own config/app.php 'providers'
      // list (old-style bootstrap) whenever it runs on its own. That list is
      // never consulted by a host application (like the proprietary customer
      // application) that requires this package over Composer - the only
      // thing a host app knows about is this service provider (registered
      // via Composer package auto-discovery). So explicitly register the
      // package's other providers here too, making this the single, robust
      // entry point for both modes.
      //
      // Note: these classes intentionally live under
      // Ledningssystemet\Ledningssystemet\Providers (this package's own
      // namespace) rather than App\Providers. A previous version kept them
      // under App\Providers, which Composer's autoloader also merges with
      // the host app's own "App\" namespace directory. That worked only by
      // accident (host apps normally define their own App\Providers\* with
      // the very same class names, e.g. App\Providers\AppServiceProvider,
      // which would silently shadow this package's version the moment the
      // host app added one - registering nothing, with no visible error).
      $this->app->register(AppServiceProvider::class);
      $this->app->register(AuthServiceProvider::class);
      $this->app->register(EventServiceProvider::class);
      $this->app->register(RouteServiceProvider::class);
      $this->app->register(FortifyServiceProvider::class);

      // Broadcasting is intentionally left disabled by default, mirroring
      // this package's own config/app.php (standalone mode), where
      // Providers\BroadcastServiceProvider is also commented out.
   }

   /**
    * The base names (without extension) of every config file shipped by this
    * package, e.g. ['activitylog', 'app', 'auth', ..., 'view'].
    *
    * @return array<int, string>
    */
   protected function configFiles(): array
   {
      return collect(Finder::create()->files()->name('*.php')->in(__DIR__ . '/../config'))
         ->map(fn ($file) => $file->getBasename('.php'))
         ->sort()
         ->values()
         ->all();
   }
}
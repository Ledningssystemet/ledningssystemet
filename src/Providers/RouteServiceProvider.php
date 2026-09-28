<?php

namespace Ledningssystemet\Ledningssystemet\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Ledningssystemet\Ledningssystemet\Http\Middleware\Authenticate;
use Ledningssystemet\Ledningssystemet\Http\Middleware\AuthProxy;
use Ledningssystemet\Ledningssystemet\Http\Middleware\JsonOnly;
use Ledningssystemet\Ledningssystemet\Http\Middleware\OnlyEnabledUsers;
use Ledningssystemet\Ledningssystemet\Http\Middleware\RedirectIfAuthenticated;
use Ledningssystemet\Ledningssystemet\Http\Middleware\RequireMfaEnforcement;
use Ledningssystemet\Ledningssystemet\Http\Middleware\ValidateSignature;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerMiddlewareAliases();
        $this->configureRateLimiting();

        $this->routes(function () {
            // Use paths relative to this package rather than base_path(),
            // since when this package is required as a Composer dependency
            // (e.g. by the proprietary customer application), base_path()
            // resolves to the *host* application's root, which does not
            // have these route files.
            Route::middleware(['api', 'jsononly', 'auth:sanctum', 'usersenabled'])
                ->prefix('api')
                ->group(__DIR__ . '/../../routes/api.php');

            Route::middleware(['web', 'usersenabled'])
                ->group(__DIR__ . '/../../routes/web.php');
        });
    }

    /**
     * Register middleware used by the package routes in both standalone and host applications.
     */
    protected function registerMiddlewareAliases(): void
    {
        $router = $this->app->make(Router::class);

        foreach ([
            'auth' => Authenticate::class,
            'authproxy' => AuthProxy::class,
            'guest' => RedirectIfAuthenticated::class,
            'jsononly' => JsonOnly::class,
            'mfa.enforced' => RequireMfaEnforcement::class,
            'signed' => ValidateSignature::class,
            'usersenabled' => OnlyEnabledUsers::class,
        ] as $name => $middleware) {
            $router->aliasMiddleware($name, $middleware);
        }
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->id ?: $request->ip());
        });
    }
}

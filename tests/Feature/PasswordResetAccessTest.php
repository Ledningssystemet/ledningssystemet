<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Auth;
use Ledningssystemet\Ledningssystemet\Http\Middleware\PasswordResetAccess;
use Ledningssystemet\Ledningssystemet\Http\Middleware\RedirectIfAuthenticated;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Exercise the registered route access middleware without database, mail or session I/O.
$router = $app->make('router');
$checks = 0;

foreach ([false, true] as $passwordLoginEnabled) {
    config(['ledningssystemet.login_allow_passwordlogin' => $passwordLoginEnabled]);

    foreach (['guest', 'user', 'admin'] as $actor) {
        Auth::forgetGuards();
        $user = $actor === 'guest' ? null : new class(['id' => 1, 'admin' => $actor === 'admin']) extends GenericUser
        {
            public function hasPermissionTo(string $permission): bool
            {
                return $permission === 'systemadministrator.edit' && $this->admin;
            }
        };

        if ($user !== null) {
            Auth::guard()->setUser($user);
        }

        foreach ([
            ['GET', '/forgot-password', 'password.request'],
            ['POST', '/forgot-password', 'password.email'],
            ['GET', '/reset-password/test-token', 'password.reset'],
            ['POST', '/reset-password', 'password.update'],
        ] as [$method, $uri, $name]) {
            $request = Request::create($uri, $method);
            $request->setUserResolver(fn () => $user);
            $route = $router->getRoutes()->match($request);
            $request->setRouteResolver(fn () => $route);
            $middleware = $router->gatherRouteMiddleware($route);

            if ($route->getName() !== $name || !in_array(PasswordResetAccess::class, $middleware, true)) {
                throw new RuntimeException("Missing password reset protection on {$method} {$uri}");
            }

            // Include any guest middleware so a misplaced route group cannot block administrators.
            $accessMiddleware = array_values(array_filter(
                $middleware,
                fn (string $class) => in_array(explode(':', $class)[0], [
                    PasswordResetAccess::class,
                    RedirectIfAuthenticated::class,
                ], true),
            ));

            try {
                $response = $app->make(Pipeline::class)
                    ->send($request)
                    ->through($accessMiddleware)
                    ->then(fn () => new Response('', 204));
                $status = $response->getStatusCode();
            } catch (HttpException $exception) {
                $status = $exception->getStatusCode();
            }

            $expected = $actor === 'admin' ? 204 : (!$passwordLoginEnabled ? 403 : ($actor === 'guest' ? 204 : 302));
            if ($status !== $expected) {
                throw new RuntimeException("{$actor}: {$method} {$uri} returned {$status}, expected {$expected}");
            }
            if ($status === 302 && $response->getTargetUrl() !== url('/')) {
                throw new RuntimeException('Authenticated non-admin users must be redirected home.');
            }

            $checks++;
        }
    }
}

$keepalive = $router->getRoutes()->match(Request::create('/auth/keepalive', 'POST'));
foreach ($router->gatherRouteMiddleware($keepalive) as $middleware) {
    if (explode(':', $middleware)[0] === RedirectIfAuthenticated::class) {
        throw new RuntimeException('Session keep-alive must also be available on administrator reset pages.');
    }
}

echo "Password reset access: {$checks} cases passed; administrator keep-alive available.\n";

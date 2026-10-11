<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Ledningssystemet\Ledningssystemet\Http\Controllers\Auth\LoginController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoginControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Application::setInstance(null);
        parent::tearDown();
    }

    #[DataProvider('loginConfigurations')]
    public function testLoginRouting(bool $passwordLoginEnabled, array $providers, ?string $redirectProvider): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository([
            'ledningssystemet' => ['login_allow_passwordlogin' => $passwordLoginEnabled],
            'services' => [
                'azure' => ['client_id' => null, 'client_secret' => null],
                'google' => ['client_id' => null, 'client_secret' => null],
            ],
        ]));

        foreach ($providers as $provider) {
            $app['config']->set('services.'.$provider, [
                'client_id' => 'test-client',
                'client_secret' => 'test-secret',
                'redirect' => 'https://example.test/sso/'.$provider.'/callback',
            ]);
        }

        $router = new Router($app['events'], $app);
        $router->get('/sso/{provider}', fn () => null)->name('sso.redirect');
        $router->get('/sso/{provider}/callback', fn () => null)->name('sso.callback');
        foreach (['login', 'password.email', 'auth.keepalive'] as $name) {
            $router->get('/'.$name, fn () => null)->name($name);
        }
        $router->getRoutes()->refreshNameLookups();
        $url = new UrlGenerator($router->getRoutes(), Request::create('https://example.test'));
        $app->instance('url', $url);
        $app->instance('redirect', new Redirector($url));
        $app->instance('translator', new Translator(new ArrayLoader(), 'en'));

        $viewFactory = $this->createMock(Factory::class);
        $app->instance(Factory::class, $viewFactory);

        if ($redirectProvider !== null) {
            $viewFactory->expects($this->never())->method('make');
            $response = (new LoginController())->show();

            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame('https://example.test/sso/'.$redirectProvider, $response->getTargetUrl());
        } elseif (!$passwordLoginEnabled && $providers === []) {
            $viewFactory->expects($this->never())->method('make');
            $this->expectException(HttpException::class);
            $this->expectExceptionMessage('No authentication method is configured');

            (new LoginController())->show();
        } else {
            $view = $this->createMock(View::class);
            $viewFactory->expects($this->once())->method('make')
                ->with('auth.react-shell', $this->callback(function (array $data) use ($passwordLoginEnabled, $providers): bool {
                    return $data['props']['passwordLoginEnabled'] === $passwordLoginEnabled
                        && array_column($data['props']['ssoProviders'], 'name') === $providers;
                }), [])
                ->willReturn($view);

            $this->assertSame($view, (new LoginController())->show());
        }
    }

    public static function loginConfigurations(): array
    {
        return [
            'azure only without password' => [false, ['azure'], 'azure'],
            'google only without password' => [false, ['google'], 'google'],
            'multiple providers without password' => [false, ['azure', 'google'], null],
            'no authentication method' => [false, [], null],
            'password only' => [true, [], null],
            'password and azure' => [true, ['azure'], null],
            'password and google' => [true, ['google'], null],
            'password and multiple providers' => [true, ['azure', 'google'], null],
        ];
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class PluginRuntimeTest extends TestCase
{
    private string $pluginRoot;

    protected function setUp(): void
    {
        $this->pluginRoot = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'plugins-'.Str::uuid();

        $this->writePluginFixture($this->pluginRoot);
        $this->setPluginEnvironment($this->pluginRoot);

        parent::setUp();

        config()->set('authentication.login_mode', 'hybrid');
        config()->set('authentication.oauth.enabled', false);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->pluginRoot);
        $this->setPluginEnvironment('');

        parent::tearDown();
    }

    public function test_plugin_backend_is_loaded_and_intercepts_requests(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Test-Plugin', 'handled');
        $response->assertSee('"pluginFixture":"loaded"', false);

        $this->assertSame('registered', app('plugin-fixture.binding'));
        $this->assertTrue((bool) app('plugin-fixture.runtime-registered'));
    }

    public function test_plugin_frontend_manifest_is_exposed_to_the_browser_runtime(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('type="importmap"', false);
        $response->assertSee('plugin:test-plugin', false);
        $response->assertSee('/plugin-assets/test-plugin/frontend/index.js', false);
        $response->assertSee('"buttonLabel":"Plugin button"', false);
        $response->assertSee('__APP_PLUGIN_RUNTIME__', false);
    }

    public function test_plugin_frontend_assets_are_served_from_the_public_plugin_directory(): void
    {
        $response = $this->get('/plugin-assets/test-plugin/frontend/index.js');

        $response->assertOk();
        $this->assertStringContainsString('max-age=3600', (string) $response->headers->get('Cache-Control'));
    }

    public function test_plugin_asset_route_rejects_non_public_paths(): void
    {
        $this->get('/plugin-assets/test-plugin/backend/plugin.php')->assertNotFound();
        $this->get('/plugin-assets/test-plugin/frontend/../backend/plugin.php')->assertNotFound();
    }

    private function setPluginEnvironment(string $value): void
    {
        putenv('PLUGIN_PATHS='.$value);
        $_ENV['PLUGIN_PATHS'] = $value;
        $_SERVER['PLUGIN_PATHS'] = $value;
    }

    private function writePluginFixture(string $pluginRoot): void
    {
        $pluginPath = $pluginRoot.DIRECTORY_SEPARATOR.'test-plugin';
        $backendPath = $pluginPath.DIRECTORY_SEPARATOR.'backend';
        $frontendPath = $pluginPath.DIRECTORY_SEPARATOR.'frontend';

        $this->ensureDirectoryExists($backendPath);
        $this->ensureDirectoryExists($frontendPath);

        file_put_contents($pluginPath.DIRECTORY_SEPARATOR.'plugin.json', <<<'JSON'
{
    "id": "test-plugin",
    "name": "Test Plugin",
    "version": "1.0.0",
    "backend": {
        "entry": "backend/plugin.php",
        "providers": [
            "backend/provider.php"
        ]
    },
    "frontend": {
        "entry": "frontend/index.js"
    }
}
JSON);

        file_put_contents($backendPath.DIRECTORY_SEPARATOR.'provider.php', <<<'PHP'
<?php

use Illuminate\Support\ServiceProvider;

return new class(app()) extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('plugin-fixture.binding', 'registered');
    }
};
PHP);

        file_put_contents($backendPath.DIRECTORY_SEPARATOR.'plugin.php', <<<'PHP'
<?php

use App\Plugins\Plugin;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

return new class extends Plugin
{
    public function register(Application $app): void
    {
        $app->instance('plugin-fixture.runtime-registered', true);
    }

    public function handleRequest(Request $request, \Closure $next): mixed
    {
        $response = $next($request);
        $response->headers->set('X-Test-Plugin', 'handled');

        return $response;
    }

    public function extendInertiaShared(array $shared, Request $request): array
    {
        $shared['pluginFixture'] = 'loaded';

        return $shared;
    }

    public function frontendConfig(?Request $request = null): array
    {
        return [
            'buttonLabel' => 'Plugin button',
        ];
    }
};
PHP);

        file_put_contents($frontendPath.DIRECTORY_SEPARATOR.'index.js', <<<'JS'
export default function registerPlugin(api) {
    api.registerSlot('page.header.actions', (context) => window.React.createElement(
        'button',
        {
            type: 'button',
            className: 'rounded-md border px-3 py-2 text-sm',
            'data-plugin-id': api.id,
            'data-route-key': context?.route?.key ?? 'unknown',
        },
        api.context.buttonLabel ?? 'plugin button',
    ));

    api.registerAxiosRequestInterceptor((config) => {
        config.headers = config.headers ?? {};
        config.headers['X-Plugin-Test'] = api.id;

        return config;
    });

    api.registerFetchInterceptor({
        before(request) {
            const headers = new Headers(request.init?.headers ?? {});
            headers.set('X-Plugin-Test', api.id);

            return {
                ...request,
                init: {
                    ...(request.init ?? {}),
                    headers,
                },
            };
        },
    });
}
JS);
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        mkdir($path, 0777, true);
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $currentPath = $path.DIRECTORY_SEPARATOR.$item;

            if (is_dir($currentPath)) {
                $this->deleteDirectory($currentPath);
                continue;
            }

            @unlink($currentPath);
        }

        @rmdir($path);
    }
}


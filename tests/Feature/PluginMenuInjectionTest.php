<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PluginMenuInjectionTest extends TestCase
{
    private string $pluginRoot;

    protected function setUp(): void
    {
        $this->pluginRoot = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'plugins-menu-'.Str::uuid();

        $this->writePluginFixture($this->pluginRoot);
        $this->setPluginEnvironment($this->pluginRoot);

        parent::setUp();

        config()->set('authentication.login_mode', 'hybrid');
        config()->set('authentication.oauth.enabled', false);

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('enabled')->default(true);
                $table->string('remember_token', 100)->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->pluginRoot);
        $this->setPluginEnvironment('');

        parent::tearDown();
    }

    public function test_authenticated_users_receive_plugin_navigation_items_from_manifest(): void
    {
        $this->actingAs($this->createUser('Plugin Menu User', 'plugin.menu.user@example.com'));

        $response = $this->get('/app');

        $response->assertOk();
        $response->assertSee('"label":"Plugin tools"', false);
        $response->assertSee('"key":"plugin-reports"', false);
        $response->assertSee('"key":"plugin-register"', false);
        $response->assertDontSee('Duplicate plugin route', false);
        $response->assertDontSee('Invalid item without icon', false);
    }

    public function test_guest_requests_do_not_receive_plugin_navigation_items(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('"label":"Plugin tools"', false);
        $response->assertDontSee('"key":"plugin-reports"', false);
    }

    private function createUser(string $name, string $email): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function setPluginEnvironment(string $value): void
    {
        putenv('PLUGIN_PATHS='.$value);
        $_ENV['PLUGIN_PATHS'] = $value;
        $_SERVER['PLUGIN_PATHS'] = $value;
    }

    private function writePluginFixture(string $pluginRoot): void
    {
        $pluginPath = $pluginRoot.DIRECTORY_SEPARATOR.'plugin-menu';
        $this->ensureDirectoryExists($pluginPath);

        file_put_contents($pluginPath.DIRECTORY_SEPARATOR.'plugin.json', <<<'JSON'
{
    "id": "plugin-menu",
    "name": "Plugin Menu",
    "version": "1.0.0",
    "navigation": {
        "categories": [
            {
                "label": "Plugin tools",
                "categoryIcon": "Settings",
                "columns": [
                    {
                        "heading": "Extensions",
                        "items": [
                            {
                                "key": "plugin-reports",
                                "label": "Plugin reports",
                                "icon": "FileText",
                                "description": "Reports added by plugin",
                                "href": "/app/plugin-reports"
                            }
                        ]
                    }
                ]
            },
            {
                "label": "Inventory",
                "columns": [
                    {
                        "heading": "Way of working",
                        "items": [
                            {
                                "key": "plugin-register",
                                "label": "Plugin register",
                                "icon": "Database",
                                "href": "/app/plugin-register"
                            },
                            {
                                "key": "plugin-duplicate-process-route",
                                "label": "Duplicate plugin route",
                                "icon": "Database",
                                "href": "/app/processes"
                            },
                            {
                                "key": "plugin-invalid-no-icon",
                                "label": "Invalid item without icon"
                            }
                        ]
                    }
                ]
            }
        ]
    }
}
JSON);
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


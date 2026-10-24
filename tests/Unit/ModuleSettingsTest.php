<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Laravel\Pennant\Feature;
use Ledningssystemet\Ledningssystemet\Http\Controllers\SystemSettingsController;
use Ledningssystemet\Ledningssystemet\Http\Middleware\ModuleEnabled;
use Ledningssystemet\Ledningssystemet\Models\User;
use Ledningssystemet\Ledningssystemet\Support\Modules;
use Ledningssystemet\Ledningssystemet\Support\SystemSettings;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ModuleSettingsTest extends TestCase
{
    private string $environmentDirectory;

    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for module settings tests.');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array',
            'session.driver' => 'array',
            'ledningssystemet.mfa_enabled' => false,
            'debugbar.enabled' => false,
        ]);
        $this->app->instance('env', 'testing');
        DB::purge('sqlite');
        Feature::flushCache();

        $this->environmentDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'module-settings-'.bin2hex(random_bytes(8));
        mkdir($this->environmentDirectory);
        $this->app->useEnvironmentPath($this->environmentDirectory)->loadEnvironmentFrom('.env');
        $keys = array_map(fn ($module) => 'DISABLE_'.strtoupper($module), array_keys(Modules::LABELS));
        $keys[] = 'COMPANY_DATA_PRIVACY_CONTACT_NAME';
        $keys[] = 'COMPANY_DATA_PRIVACY_CONTACT_EMAIL';
        foreach ($keys as $key) {
            $this->originalEnvironment[$key] = Env::getRepository()->get($key);
            Env::getRepository()->clear($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $key => $value) {
            if ($value === null) {
                Env::getRepository()->clear($key);
            } else {
                Env::getRepository()->set($key, $value);
            }
        }
        if (isset($this->environmentDirectory)) {
            $file = $this->environmentDirectory.DIRECTORY_SEPARATOR.'.env';
            if (is_file($file)) {
                unlink($file);
            }
            rmdir($this->environmentDirectory);
        }

        parent::tearDown();
    }

    private function migrate(string $environment = ''): void
    {
        file_put_contents($this->environmentDirectory.DIRECTORY_SEPARATOR.'.env', $environment);
        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_24_000000_create_system_settings_table.php';
        $migration->up();
    }

    private function user(array $permissions = []): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes(['id' => 1, 'enabled' => true]);
        $user->shouldReceive('hasPermissionTo')->andReturnUsing(
            fn (string $permission) => in_array($permission, $permissions, true)
        );

        return $user;
    }

    public function test_migration_uses_the_existing_defaults_and_can_be_rolled_back(): void
    {
        $this->migrate();

        $this->assertSame(8, DB::table('system_settings')->count());
        foreach (array_keys(SystemSettings::CONTACT_FIELDS) as $name) {
            $this->assertSame('', SystemSettings::value($name));
        }
        foreach (array_keys(Modules::LABELS) as $module) {
            $this->assertSame($module !== 'archival', Modules::active($module));
        }
        $this->assertSame(['__laravel_null'], DB::table('system_settings')->distinct()->pluck('scope')->all());

        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_24_000000_create_system_settings_table.php';
        $migration->down();
        $this->assertFalse(Schema::hasTable('system_settings'));
    }

    public function test_migration_reads_dotenv_without_depending_on_cached_configuration(): void
    {
        config(['ledningssystemet.disable_staff' => false, 'ledningssystemet.disable_archival' => true]);
        $this->migrate(implode("\n", [
            'DISABLE_STAFF=true',
            'DISABLE_PROCESSES=(true)',
            'DISABLE_FINDING=1',
            'DISABLE_SUPPLIER=false',
            'DISABLE_GDPR=(false)',
            'DISABLE_ARCHIVAL=0',
            'COMPANY_DATA_PRIVACY_CONTACT_NAME="Privacy Contact"',
            'COMPANY_DATA_PRIVACY_CONTACT_EMAIL=privacy@example.test',
        ]));

        foreach (array_keys(Modules::LABELS) as $module) {
            $this->assertSame(in_array($module, ['supplier', 'gdpr', 'archival'], true), Modules::active($module));
        }

        Env::getRepository()->set('DISABLE_STAFF', 'false');
        Feature::flushCache();
        $this->assertTrue(Modules::inactive('staff'));
        $this->assertSame('Privacy Contact', SystemSettings::value('data_privacy_contact_name'));
        $this->assertSame('privacy@example.test', SystemSettings::value('data_privacy_contact_email'));
        Env::getRepository()->set('COMPANY_DATA_PRIVACY_CONTACT_NAME', 'Ignored environment contact');
        config(['ledningssystemet.data_privacy_contact_name' => 'Ignored config contact']);
        Feature::flushCache();
        $this->assertSame('Privacy Contact', SystemSettings::value('data_privacy_contact_name'));
    }

    public function test_exported_environment_takes_precedence_over_dotenv(): void
    {
        Env::getRepository()->set('DISABLE_STAFF', 'true');
        Env::getRepository()->set('COMPANY_DATA_PRIVACY_CONTACT_NAME', 'Exported Contact');
        Env::getRepository()->set('COMPANY_DATA_PRIVACY_CONTACT_EMAIL', 'exported@example.test');
        $this->migrate("DISABLE_STAFF=false\nCOMPANY_DATA_PRIVACY_CONTACT_NAME=Dotenv\nCOMPANY_DATA_PRIVACY_CONTACT_EMAIL=dotenv@example.test");

        $this->assertTrue(Modules::inactive('staff'));
        $this->assertSame('Exported Contact', SystemSettings::value('data_privacy_contact_name'));
        $this->assertSame('exported@example.test', SystemSettings::value('data_privacy_contact_email'));
    }

    public function test_invalid_legacy_boolean_fails_before_creating_the_table(): void
    {
        try {
            $this->migrate('DISABLE_STAFF=not-a-boolean');
            $this->fail('Invalid legacy flags must not silently change the enabled modules.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('DISABLE_STAFF', $exception->getMessage());
            $this->assertFalse(Schema::hasTable('system_settings'));
        }
    }

    #[DataProvider('administratorPermissions')]
    public function test_only_administrators_can_manage_settings(array $permissions, bool $allowed): void
    {
        $this->actingAs($this->user($permissions));

        $this->assertSame($allowed, Gate::allows('manage-system-settings'));
        if (! $allowed) {
            $this->get('/systemadmin/settings')->assertForbidden();
            $this->put('/systemadmin/settings', ['modules' => []])->assertForbidden();
        }
    }

    public static function administratorPermissions(): array
    {
        return [
            'ordinary user' => [[], false],
            'system administrator' => [['systemadministrator.edit'], true],
            'superadmin' => [['superadmin.edit'], true],
        ];
    }

    public function test_guest_cannot_read_or_update_settings(): void
    {
        $this->get('/systemadmin/settings')->assertRedirect('/login');
        $this->put('/systemadmin/settings')->assertRedirect('/login');
    }

    public function test_administrator_updates_persist_globally_and_invalidate_pennant_values(): void
    {
        $this->migrate();
        $this->actingAs($this->user(['systemadministrator.edit']));
        $this->assertTrue(Modules::active('staff'));
        $values = array_fill_keys(array_keys(Modules::LABELS), '0');
        $values['archival'] = '1';

        $this->put('/systemadmin/settings', [
            'modules' => $values,
            'data_privacy_contact_name' => 'Updated Contact',
            'data_privacy_contact_email' => 'updated@example.test',
        ])
            ->assertRedirect(route('systemadmin.settings'))
            ->assertSessionHas('status', __('System settings saved.'));

        $this->assertFalse(Modules::active('staff'));
        $this->assertTrue(Modules::active('archival'));
        Feature::flushCache();
        $this->actingAs($this->user());
        $this->assertFalse(Modules::active('staff'));
        $this->assertTrue(Modules::active('archival'));
        $this->assertSame('Updated Contact', SystemSettings::value('data_privacy_contact_name'));
        $this->assertSame('updated@example.test', SystemSettings::value('data_privacy_contact_email'));
        $this->assertSame(8, DB::table('system_settings')->count());
    }

    public function test_administrator_form_reflects_persisted_values(): void
    {
        $this->migrate("COMPANY_DATA_PRIVACY_CONTACT_NAME='Contact <name>'\nCOMPANY_DATA_PRIVACY_CONTACT_EMAIL=privacy@example.test");
        $this->actingAs($this->user(['systemadministrator.edit']));
        $this->app->setLocale('en');
        $view = (new SystemSettingsController)->index();
        $this->assertSame('systemadmin.settings', $view->name());

        $template = str_replace(
            "@extends('layouts.master')",
            '',
            file_get_contents(dirname(__DIR__, 2).'/resources/views/systemadmin/settings.blade.php')
        );
        $html = Blade::render($template."\n@yield('container')", $view->getData() + ['errors' => new ViewErrorBag]);

        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
        $this->assertSame(6, substr_count($html, 'type="checkbox"'));
        $this->assertSame(5, substr_count($html, 'checked'));
        $this->assertStringContainsString('value="Contact &lt;name&gt;"', $html);
        $this->assertStringContainsString('value="privacy@example.test"', $html);
        $this->assertStringContainsString('name="data_privacy_contact_name"', $html);
        $this->assertStringContainsString('name="data_privacy_contact_email"', $html);
        foreach (array_keys(Modules::LABELS) as $module) {
            $this->assertStringContainsString('name="modules['.$module.']"', $html);
        }
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_do_not_partially_update_modules(array $values): void
    {
        $this->migrate();
        $this->actingAs($this->user(['systemadministrator.edit']));
        $before = DB::table('system_settings')->pluck('value', 'name')->all();

        $this->putJson('/systemadmin/settings', [
            'modules' => $values,
            'data_privacy_contact_name' => 'Must not be saved',
            'data_privacy_contact_email' => 'unsaved@example.test',
        ])->assertUnprocessable();

        $this->assertSame($before, DB::table('system_settings')->pluck('value', 'name')->all());
    }

    public static function invalidSettings(): array
    {
        $values = array_fill_keys(array_keys(Modules::LABELS), '0');

        return [
            'missing values' => [['staff' => '0']],
            'invalid boolean' => [array_replace($values, ['staff' => 'maybe'])],
            'unknown module' => [$values + ['unknown' => '1']],
        ];
    }

    public function test_contact_details_can_be_cleared(): void
    {
        $this->migrate("COMPANY_DATA_PRIVACY_CONTACT_NAME=Contact\nCOMPANY_DATA_PRIVACY_CONTACT_EMAIL=privacy@example.test");
        $this->actingAs($this->user(['systemadministrator.edit']));

        $this->put('/systemadmin/settings', [
            'modules' => array_fill_keys(array_keys(Modules::LABELS), '1'),
            'data_privacy_contact_name' => '',
            'data_privacy_contact_email' => '',
        ])->assertRedirect(route('systemadmin.settings'));

        Feature::flushCache();
        foreach (array_keys(SystemSettings::CONTACT_FIELDS) as $name) {
            $this->assertSame('', SystemSettings::value($name));
        }
    }

    #[DataProvider('invalidContacts')]
    public function test_invalid_contact_details_do_not_update_any_settings(array $contact): void
    {
        $this->migrate();
        $this->actingAs($this->user(['systemadministrator.edit']));
        $before = DB::table('system_settings')->pluck('value', 'name')->all();

        $this->putJson('/systemadmin/settings', [
            'modules' => array_fill_keys(array_keys(Modules::LABELS), '0'),
        ] + $contact)->assertUnprocessable();

        $this->assertSame($before, DB::table('system_settings')->pluck('value', 'name')->all());
    }

    public static function invalidContacts(): array
    {
        return [
            'invalid email' => [['data_privacy_contact_name' => 'Contact', 'data_privacy_contact_email' => 'invalid']],
            'missing contact fields' => [[]],
            'invalid name type' => [['data_privacy_contact_name' => ['Contact'], 'data_privacy_contact_email' => '']],
            'name too long' => [['data_privacy_contact_name' => str_repeat('x', 256), 'data_privacy_contact_email' => '']],
            'email too long' => [['data_privacy_contact_name' => '', 'data_privacy_contact_email' => str_repeat('x', 256).'@example.test']],
        ];
    }

    public function test_processing_register_displays_database_contact_details(): void
    {
        $this->migrate("COMPANY_DATA_PRIVACY_CONTACT_NAME='Contact <name>'\nCOMPANY_DATA_PRIVACY_CONTACT_EMAIL=privacy@example.test");
        $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/inventory/dataprocessingregister.blade.php');
        $lines = array_filter(explode("\n", $template), fn ($line) => str_contains($line, "SystemSettings::value('data_privacy_contact_name')"));
        $this->assertCount(3, $lines);
        $html = Blade::render(implode("\n", $lines));

        $this->assertSame(3, substr_count($html, 'Contact &lt;name&gt; (privacy@example.test)'));
    }

    public function test_module_middleware_uses_current_values_without_reregistering_routes(): void
    {
        $this->migrate();
        $middleware = new ModuleEnabled;
        $request = Request::create('/staff/employees');
        $next = fn () => response('enabled');
        $this->assertSame('enabled', $middleware->handle($request, $next, 'staff')->getContent());

        Feature::store('ledningssystemet')->for(null)->deactivate('staff');
        try {
            $middleware->handle($request, $next, 'staff');
            $this->fail('Disabled modules must return 404.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        Feature::store('ledningssystemet')->for(null)->activate('staff');
        $this->assertSame('enabled', $middleware->handle($request, $next, 'staff')->getContent());
    }

    public function test_module_routes_are_always_registered_with_runtime_checks(): void
    {
        $routes = app('router')->getRoutes();
        foreach ([
            '/user/me' => 'staff',
            '/staff/employees' => 'staff',
            '/systemadmin/roles' => 'staff',
            '/inventory/processes' => 'processes',
            '/measure/objectives' => 'processes',
            '/inventory/suppliers' => 'supplier',
            '/inventory/dataprocessingregister' => 'gdpr',
            '/inventory/docmgmtplan' => 'archival',
            '/api/v1/processDataProcessingRegister/controller' => 'gdpr',
            '/api/v1/documentManagementPlan' => 'archival',
            '/api/v1/LibraryDocument/1/download' => 'processes',
        ] as $path => $module) {
            $route = $routes->match(Request::create($path));
            $this->assertContains(ModuleEnabled::class.':'.$module, $route->gatherMiddleware());
        }
    }

    public function test_compiled_routes_respond_to_module_changes_immediately(): void
    {
        $this->migrate();
        $this->actingAs($this->user(['superadmin.edit']));
        $router = app('router');
        $route = $router->getRoutes()->match(Request::create('/staff/employees'));
        $route->setAction(array_replace($route->getAction(), ['uses' => fn () => response('enabled')]));
        $router->setCompiledRoutes($router->getRoutes()->compile());

        $this->get('/staff/employees')->assertOk()->assertSee('enabled');
        Feature::store('ledningssystemet')->for(null)->deactivate('staff');
        $this->get('/staff/employees')->assertNotFound();
        Feature::store('ledningssystemet')->for(null)->activate('staff');
        $this->get('/staff/employees')->assertOk()->assertSee('enabled');
    }

    public function test_missing_settings_fail_explicitly_instead_of_changing_module_defaults(): void
    {
        $this->migrate();
        DB::table('system_settings')->where('name', 'staff')->delete();
        Feature::flushCache();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Missing module setting [staff]. Run database migrations.');
        Modules::active('staff');
    }
}

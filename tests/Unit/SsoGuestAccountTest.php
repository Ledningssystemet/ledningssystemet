<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ledningssystemet\Ledningssystemet\Http\Controllers\Auth\SsoController;
use Ledningssystemet\Ledningssystemet\Http\Middleware\TrustProxies;
use Ledningssystemet\Ledningssystemet\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SsoGuestAccountTest extends TestCase
{
    private array $originalDatabaseConfig;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for SSO guest account tests.');
        }

        $this->originalDatabaseConfig = config('database');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('external_id')->nullable()->unique();
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('manager_user_id')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        User::unsetEventDispatcher();
    }

    protected function tearDown(): void
    {
        if (isset($this->originalDatabaseConfig)) {
            DB::purge('sqlite');
            config(['database' => $this->originalDatabaseConfig]);
            User::setEventDispatcher($this->app['events']);
        }

        parent::tearDown();
    }

    public function test_azure_guest_upn_matches_and_links_existing_user(): void
    {
        DB::table('users')->insert([
            'name' => 'Guest User',
            'email' => 'guest@externaldomain.com',
            'enabled' => true,
        ]);
        $controller = new class extends SsoController {
            public function resolve(string $provider, string $email, string $providerUserId): User
            {
                return $this->resolveUser($provider, $email, 'Guest User', $providerUserId);
            }
        };

        $user = $controller->resolve('azure', 'guest_externaldomain.com#EXT#@tenant.onmicrosoft.com', 'guest-id');

        $this->assertSame('guest@externaldomain.com', $user->email);
        $this->assertSame('azure:guest-id', $user->external_id);

        DB::table('users')->where('id', $user->id)->update(['email' => 'changed@example.com']);
        $linkedUser = $controller->resolve('azure', 'guest_externaldomain.com#EXT#@tenant.onmicrosoft.com', 'guest-id');

        $this->assertSame($user->id, $linkedUser->id);
        $this->assertSame('guest@externaldomain.com', $linkedUser->email);
    }

    public function test_guest_email_normalization_is_azure_only_and_does_not_create_users(): void
    {
        DB::table('users')->insert([
            'name' => 'Guest User',
            'email' => 'guest@externaldomain.com',
            'enabled' => true,
        ]);
        $controller = new class extends SsoController {
            public function resolve(string $provider, string $email): User
            {
                return $this->resolveUser($provider, $email, 'Guest User', 'guest-id');
            }
        };

        try {
            $controller->resolve('google', 'guest_externaldomain.com#EXT#@tenant.onmicrosoft.com');
            $this->fail('Guest email normalization must not apply to other providers.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_non_guest_azure_email_continues_to_match_normally(): void
    {
        DB::table('users')->insert([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'enabled' => true,
        ]);
        $controller = new class extends SsoController {
            public function resolve(string $email): User
            {
                return $this->resolveUser('azure', $email, 'Regular User', 'regular-id');
            }
        };

        $user = $controller->resolve(' USER@EXAMPLE.COM ');

        $this->assertSame('user@example.com', $user->email);
        $this->assertSame('azure:regular-id', $user->external_id);
    }

    public function test_unmatched_azure_guest_is_rejected_without_creating_a_user(): void
    {
        $controller = new class extends SsoController {
            public function resolve(string $email): User
            {
                return $this->resolveUser('azure', $email, 'Unknown Guest', 'unknown-id');
            }
        };

        try {
            $controller->resolve('unknown_externaldomain.com#EXT#@tenant.onmicrosoft.com');
            $this->fail('An unmatched guest must not be allowed to sign in.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_missing_provider_id_does_not_match_an_unlinked_account(): void
    {
        DB::table('users')->insert([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'enabled' => true,
        ]);
        $controller = new class extends SsoController {
            public function resolve(string $email): User
            {
                return $this->resolveUser('azure', $email, 'Unknown Guest', null);
            }
        };

        try {
            $controller->resolve('unknown_externaldomain.com#EXT#@tenant.onmicrosoft.com');
            $this->fail('A missing provider ID must not match a user with no external ID.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame('existing@example.com', DB::table('users')->value('email'));
    }

    public function test_forwarded_host_and_https_are_trusted_from_configured_proxy(): void
    {
        config(['app.trusted_proxies' => '127.0.0.1']);
        $request = Request::create('http://internal.test/oauthcallback', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_HOST' => 'app.example.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        (new TrustProxies())->handle($request, fn () => response('ok'));

        $this->assertSame('https://app.example.test', $request->getSchemeAndHttpHost());
    }
}

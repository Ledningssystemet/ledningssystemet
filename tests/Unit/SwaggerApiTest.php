<?php

namespace Tests\Unit;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Ledningssystemet\Ledningssystemet\Models\User;
use Mockery;
use Tests\TestCase;

class SwaggerApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'session.driver' => 'array',
            'ledningssystemet.mfa_enabled' => false,
            'debugbar.enabled' => false,
        ]);
        Gate::before(fn () => false);
    }

    private function user(bool $enabled = true): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->andReturn(false);
        $user->setRawAttributes(['id' => 1, 'enabled' => $enabled]);

        return $user;
    }

    public function testApiUsersCanRetrieveTheSpecificationWithoutAdminPermission(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        $this->assertFalse($user->canAny(['systemadministrator.edit']));

        $response = $this->getJson('https://example.test:8443/api/v1/swagger');
        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('swagger', '2.0')
            ->assertJsonPath('info.version', '1.0.0')
            ->assertJsonPath('host', 'example.test:8443')
            ->assertJsonPath('basePath', '/')
            ->assertJsonPath('security.0.Bearer', []);

        $specification = json_decode($response->getContent());
        $asset = $specification->paths->{'/api/v1/items/Asset'};
        $this->assertIsObject($asset->post->parameters[0]->schema->properties);
        $this->assertIsObject($asset->get->responses->{'200'}->schema->properties);
        $this->assertContains('search', array_column($asset->get->parameters, 'name'));
        $this->assertIsObject($specification->paths->{'/api/v1/items/Asset/{id}'}->patch);

        // Generation must also work more than once in the same process.
        $this->getJson('/api/v1/swagger')->assertOk();
    }

    public function testSessionUsersCanLoadTheSameEndpointForSwaggerUi(): void
    {
        config(['sanctum.stateful' => []]);
        $user = $this->user();
        $provider = Mockery::mock(UserProvider::class);
        $provider->shouldReceive('retrieveById')->once()->with(1)->andReturn($user);
        $guard = Auth::guard('web');
        $guard->setProvider($provider);
        $this->withSession([$guard->getName() => $user->getAuthIdentifier()]);
        $this->withCookie(config('session.cookie'), session()->getId());

        $this->assertSame('/api/v1/swagger', route('api.v1.swagger', [], false));
        $this->getJson('/api/v1/swagger')->assertOk()->assertJsonPath('swagger', '2.0');
    }

    public function testSessionMiddlewareIsLimitedToSwagger(): void
    {
        $routes = app('router')->getRoutes();
        $swagger = $routes->match(\Illuminate\Http\Request::create('/api/v1/swagger'));
        $status = $routes->match(\Illuminate\Http\Request::create('/api/v1/getstatus'));

        $this->assertContains('web', $swagger->gatherMiddleware());
        $this->assertNotContains('web', $status->gatherMiddleware());
    }

    public function testBearerTokenAuthenticatesWithoutASessionCookie(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for the token database.');
        }

        config([
            'sanctum.stateful' => [],
            'activitylog.enabled' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        DB::purge('sqlite');

        try {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled');
            });
            Schema::create('custom_properties', function (Blueprint $table) {
                $table->id();
                $table->string('context');
            });
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
            DB::table('users')->insert(['id' => 1, 'enabled' => true]);
            $token = User::findOrFail(1)->createToken('swagger')->plainTextToken;

            $this->withToken($token)->getJson('/api/v1/swagger')
                ->assertOk()->assertJsonPath('swagger', '2.0');
        } finally {
            DB::purge('sqlite');
        }
    }

    public function testUnauthenticatedUsersCannotRetrieveTheSpecification(): void
    {
        $this->getJson('/api/v1/swagger')->assertUnauthorized();
    }

    public function testDisabledApiUsersCannotRetrieveTheSpecification(): void
    {
        Sanctum::actingAs($this->user(false));

        $this->getJson('/api/v1/swagger')->assertUnauthorized();
    }
}

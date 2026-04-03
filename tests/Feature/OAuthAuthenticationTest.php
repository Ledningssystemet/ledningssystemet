<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as OAuthUserContract;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class OAuthAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('authentication.login_mode', 'hybrid');
        config()->set('authentication.oauth.enabled', true);
        config()->set('authentication.oauth.provider', 'google');
        config()->set('authentication.oauth.client_id', 'client-id');
        config()->set('authentication.oauth.client_secret', 'client-secret');
        config()->set('authentication.oauth.redirect', 'http://localhost/oauth/workplace/callback');

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The pdo_sqlite extension is required for OAuth feature tests.');
        }

        // Keep tests independent from the baseline SQL migration format.
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('enabled')->default(false);
            $table->rememberToken();
            $table->string('externalproviderid')->nullable()->unique();
            $table->string('external_id')->nullable()->unique();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_workplace_redirect_route_redirects_to_provider(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get('/oauth/workplace/redirect');

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_callback_creates_and_logs_in_user(): void
    {
        $provider = Mockery::mock(Provider::class);
        $oauthUser = Mockery::mock(OAuthUserContract::class);

        $oauthUser->shouldReceive('getId')->andReturn('google-subject-123');
        $oauthUser->shouldReceive('getEmail')->andReturn('user@example.com');
        $oauthUser->shouldReceive('getName')->andReturn('OAuth User');

        $provider->shouldReceive('user')->once()->andReturn($oauthUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get('/oauth/workplace/callback?code=test-code&state=test-state');

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
            'externalproviderid' => 'google',
            'external_id' => 'google-subject-123',
            'enabled' => 1,
        ]);
    }

    public function test_oauth_redirect_returns_not_found_when_oauth_is_not_configured(): void
    {
        config()->set('authentication.oauth.enabled', false);

        $response = $this->get('/oauth/workplace/redirect');

        $response->assertNotFound();
    }
}


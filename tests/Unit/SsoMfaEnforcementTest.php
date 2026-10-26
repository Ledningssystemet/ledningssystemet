<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Socialite;
use Illuminate\Validation\ValidationException;
use Ledningssystemet\Ledningssystemet\Http\Controllers\Auth\SsoController;
use Ledningssystemet\Ledningssystemet\Http\Controllers\Auth\TwoFactorChallengeController;
use Ledningssystemet\Ledningssystemet\Models\User;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Mockery;
use SocialiteProviders\Manager\Config as SocialiteConfig;
use Tests\TestCase;

class SsoMfaEnforcementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ledningssystemet.mfa_enabled' => true,
            'ledningssystemet.mfa_enforced' => true,
            'services.azure' => [
                'client_id' => 'test-client',
                'client_secret' => 'test-secret',
                'redirect' => 'https://example.test/oauthcallback',
                'scope' => 'openid,email,profile',
                'trust_idp_mfa' => false,
                'mfa_acr_values' => [],
            ],
        ]);

        if (!Route::has('two-factor.login')) {
            Route::get('/test-two-factor-challenge', fn () => null)->name('two-factor.login');
        }

        if (!Route::has('mfa.enforcement')) {
            Route::get('/test-mfa-enforcement', fn () => null)->name('mfa.enforcement');
        }
    }

    public function test_local_challenge_is_required_by_default_even_with_mfa_claims(): void
    {
        $this->assertCallbackRedirectsToLocalChallenge(['amr' => ['mfa']]);
    }

    public function test_enabled_idp_trust_requires_valid_mfa_assurance(): void
    {
        config(['services.azure.trust_idp_mfa' => true]);

        $this->assertCallbackRedirectsToLocalChallenge([]);
        $this->assertCallbackRedirectsToLocalChallenge(['acr' => 'urn:example:password']);

        $this->assertCallbackSkipsLocalChallenge(['amr' => ['pwd', 'otp']]);
    }

    public function test_configured_acr_value_can_assert_upstream_mfa(): void
    {
        config([
            'services.azure.trust_idp_mfa' => true,
            'services.azure.mfa_acr_values' => ['urn:example:mfa'],
        ]);

        $this->assertCallbackSkipsLocalChallenge(['acr' => 'urn:example:mfa']);
    }

    public function test_user_without_local_mfa_is_sent_to_enrollment_even_with_idp_mfa(): void
    {
        config(['services.azure.trust_idp_mfa' => true]);

        $response = $this->callback(['amr' => ['mfa']], false);

        $this->assertSame(route('mfa.enforcement'), $response->getTargetUrl());
        $this->assertTrue(Auth::check());
    }

    public function test_valid_local_challenge_completes_the_sso_session(): void
    {
        [$request, $session, $guard, $user] = $this->challengeRequest(true);
        $user->shouldReceive('save')->once()->andReturn(true);
        $guard->shouldReceive('login')->once()->with($user, false);

        $response = (new TwoFactorChallengeController())->store($request);

        $this->assertStringEndsWith(config('fortify.home'), $response->getTargetUrl());
        $this->assertSame('SSO_AZURE', $session->get('auth_mechanism'));
        $this->assertFalse($session->has('login.id'));
    }

    public function test_valid_recovery_code_completes_the_sso_session(): void
    {
        [$request, $session, $guard, $user] = $this->challengeRequest(true, 'recovery-code');
        $user->shouldReceive('replaceRecoveryCode')->once()->with('recovery-code');
        $user->shouldReceive('save')->once()->andReturn(true);
        $guard->shouldReceive('login')->once()->with($user, false);

        (new TwoFactorChallengeController())->store($request);

        $this->assertSame('SSO_AZURE', $session->get('auth_mechanism'));
        $this->assertFalse($session->has('login.id'));
    }

    public function test_invalid_local_challenge_does_not_complete_the_sso_session(): void
    {
        [$request, $session] = $this->challengeRequest(false);
        Auth::shouldReceive('guard')->never();

        try {
            (new TwoFactorChallengeController())->store($request);
            $this->fail('An invalid MFA code should be rejected.');
        } catch (ValidationException) {
            $this->assertSame(42, $session->get('login.id'));
            $this->assertFalse($session->has('auth_mechanism'));
        }
    }

    private function assertCallbackRedirectsToLocalChallenge(array $claims): void
    {
        $response = $this->callback($claims);

        $this->assertSame(route('two-factor.login'), $response->getTargetUrl());
        $this->assertSame(42, session('login.id'));
        $this->assertFalse(Auth::check());

        $dashboard = $this->withSession(session()->all())->get('/dashboard');
        $this->assertNotSame(200, $dashboard->getStatusCode());
    }

    private function assertCallbackSkipsLocalChallenge(array $claims): void
    {
        $response = $this->callback($claims);

        $this->assertNotSame(route('two-factor.login'), $response->getTargetUrl());
        $this->assertTrue(Auth::check());
    }

    private function callback(array $claims, bool $hasLocalMfa = true): \Illuminate\Http\RedirectResponse
    {
        Auth::forgetGuards();

        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill([
            'id' => 42,
            'enabled' => true,
            'two_factor_secret' => $hasLocalMfa ? 'encrypted-secret' : null,
            'two_factor_recovery_codes' => $hasLocalMfa ? '[]' : null,
            'two_factor_confirmed_at' => $hasLocalMfa ? now() : null,
        ]);
        $user->shouldReceive('save')->andReturn(true);

        $socialUser = new class($claims) {
            public function __construct(private array $claims)
            {
            }

            public function getEmail(): string
            {
                return 'user@example.test';
            }

            public function getName(): string
            {
                return 'Test User';
            }

            public function getId(): string
            {
                return 'provider-user';
            }

            public function getRaw(): array
            {
                return $this->claims;
            }
        };

        $driver = Mockery::mock();
        $driver->shouldReceive('setConfig')->once()->with(Mockery::type(SocialiteConfig::class))->andReturnSelf();
        $driver->shouldReceive('scopes')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($socialUser);
        Socialite::shouldReceive('driver')->once()->with('azure')->andReturn($driver);

        $controller = new class extends SsoController {
            public User $resolvedUser;

            protected function resolveUser(string $provider, string $email, string $name, string $providerUserId): User
            {
                return $this->resolvedUser;
            }
        };
        $controller->resolvedUser = $user;

        $session = $this->app['session']->driver();
        $session->flush();
        $session->start();
        $request = Request::create('/oauthcallback');
        $request->setLaravelSession($session);

        return $controller->callback($request, 'azure');
    }

    private function challengeRequest(bool $hasValidCode, ?string $validRecoveryCode = null): array
    {
        config(['ledningssystemet.mfa_enabled' => true]);

        $session = $this->app['session']->driver();
        $session->flush();
        $session->start();
        $session->put([
            'login.id' => 42,
            'login.auth_mechanism' => 'SSO_AZURE',
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 42, 'enabled' => true]);
        $provider = Mockery::mock();
        $provider->shouldReceive('retrieveById')->once()->with(42)->andReturn($user);
        $guard = Mockery::mock();

        Auth::shouldReceive('getProvider')->once()->andReturn($provider);

        $request = Mockery::mock(TwoFactorLoginRequest::class)->makePartial();
        $request->shouldReceive('session')->andReturn($session);
        $request->shouldReceive('setUserResolver')->once()->andReturnSelf();
        $request->shouldReceive('validRecoveryCode')->once()->andReturn($validRecoveryCode);
        if (null === $validRecoveryCode) {
            $request->shouldReceive('hasValidCode')->once()->andReturn($hasValidCode);
        }
        $request->shouldReceive('boolean')->with('remember')->andReturn(false);
        $request->shouldReceive('boolean')->with('trust_device')->andReturn(false);

        if ($hasValidCode) {
            Auth::shouldReceive('guard')->once()->with(config('fortify.guard'))->andReturn($guard);
        }

        return [$request, $session, $guard, $user];
    }
}

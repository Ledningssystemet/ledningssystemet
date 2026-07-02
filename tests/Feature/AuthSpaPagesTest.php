<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthSpaPagesTest extends TestCase
{
    public function test_forgot_password_page_is_rendered_with_inertia(): void
    {
        config()->set('authentication.login_mode', 'hybrid');
        config()->set('authentication.oauth.enabled', false);

        $response = $this->get('/forgot-password');

        $response->assertOk();
        $response->assertSee('"component":"auth\\/ForgotPassword"', false);
    }

    public function test_reset_password_page_is_rendered_with_inertia_and_props(): void
    {
        config()->set('authentication.login_mode', 'hybrid');
        config()->set('authentication.oauth.enabled', false);

        $response = $this->get('/reset-password/token-123?email=user@example.com');

        $response->assertOk();
        $response->assertSee('"component":"auth\\/ResetPassword"', false);
        $response->assertSee('"token":"token-123"', false);
        $response->assertSee('"email":"user@example.com"', false);
    }

    public function test_otp_challenge_page_is_rendered_with_inertia_when_pending_session_exists(): void
    {
        $response = $this
            ->withSession(['otp.pending_user_id' => 1])
            ->get('/otp/challenge');

        $response->assertOk();
        $response->assertSee('"component":"auth\\/OtpChallenge"', false);
        $response->assertSee('"ttlMinutes":'.config('authentication.mfa.otp_ttl_minutes'), false);
    }

    public function test_otp_challenge_redirects_to_login_when_session_is_missing(): void
    {
        $response = $this->get('/otp/challenge');

        $response->assertRedirect('/login');
    }
}


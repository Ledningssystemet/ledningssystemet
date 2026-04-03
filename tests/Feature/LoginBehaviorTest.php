<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginBehaviorTest extends TestCase
{
    public function test_guest_is_redirected_to_login_for_home_route(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_login_page_redirects_to_oauth_in_oauth_only_mode_when_configured(): void
    {
        config()->set('authentication.login_mode', 'oauth');
        config()->set('authentication.oauth.enabled', true);
        config()->set('authentication.oauth.provider', 'google');
        config()->set('authentication.oauth.client_id', 'id');
        config()->set('authentication.oauth.client_secret', 'secret');
        config()->set('authentication.oauth.redirect', 'http://localhost/oauth/workplace/callback');

        $response = $this->get('/login');

        $response->assertRedirect(route('oauth.redirect'));
    }

    public function test_login_page_shows_oauth_button_only_when_configured(): void
    {
        config()->set('authentication.login_mode', 'hybrid');
        config()->set('authentication.oauth.enabled', false);

        $withoutOauth = $this->get('/login');
        $withoutOauth->assertOk();
        $withoutOauth->assertDontSee('Logga in med ditt arbetsplatskonto');

        config()->set('authentication.oauth.enabled', true);
        config()->set('authentication.oauth.provider', 'google');
        config()->set('authentication.oauth.client_id', 'id');
        config()->set('authentication.oauth.client_secret', 'secret');
        config()->set('authentication.oauth.redirect', 'http://localhost/oauth/workplace/callback');

        $withOauth = $this->get('/login');
        $withOauth->assertOk();
        $withOauth->assertSee('Logga in med ditt arbetsplatskonto');
    }

    public function test_login_page_shows_mfa_enforced_message_when_enabled_and_enforced(): void
    {
        config()->set('authentication.login_mode', 'hybrid');
        config()->set('authentication.mfa.enabled', true);
        config()->set('authentication.mfa.enforce', true);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('MFA ar obligatoriskt');
    }
}



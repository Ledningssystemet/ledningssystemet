<?php

namespace Tests\Feature;

use Tests\TestCase;

class InertiaTranslationsPayloadTest extends TestCase
{
    public function test_login_page_includes_nested_translation_groups_in_inertia_shared_props(): void
    {
        config()->set('authentication.login_mode', 'hybrid');
        config()->set('authentication.oauth.enabled', false);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('"component":"auth\\/Login"', false);
        $response->assertSee('"translations":{"auth":{', false);
        $response->assertSee('"ui":{', false);
        $response->assertSee('"pages":{', false);
        $response->assertSee('"menu":{', false);
    }
}


<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    public function test_openapi_spec_lists_bearer_token_endpoints_and_security_scheme(): void
    {
        $response = $this->getJson('/openapi.json');

        $response->assertOk();
        $response->assertJsonPath('openapi', '3.1.0');
        $response->assertJsonPath('components.securitySchemes.bearerAuth.type', 'http');
        $response->assertJsonPath('components.securitySchemes.bearerAuth.scheme', 'bearer');
        $response->assertJsonPath('paths./api/menu/badges.get.security.0.bearerAuth.0', null);
        $response->assertJsonPath('paths./api/crud/resources.get.security.0.bearerAuth.0', null);
        $response->assertJsonPath('paths./api/tokens.get.security.0.bearerAuth.0', null);
        $response->assertJsonPath('paths./api/crud/users.get.security.0.bearerAuth.0', null);
        $response->assertJsonPath('paths./api/crud/users.post.security.0.bearerAuth.0', null);
        $response->assertJsonPath('paths./api/crud/users/{id}.get.security.0.bearerAuth.0', null);
        $response->assertJsonPath('paths./api/crud/users/{id}.patch.security.0.bearerAuth.0', null);
        $response->assertJsonPath('paths./api/crud/users/{id}.delete.security.0.bearerAuth.0', null);
        $resourceEnum = $response->json('paths./api/crud/{resource}.get.parameters.0.schema.enum');
        $this->assertIsArray($resourceEnum);
        $this->assertContains('users', $resourceEnum);

        $infoDescription = (string) $response->json('info.description');
        $this->assertStringContainsString('/api/crud/resources', $infoDescription);
        $this->assertStringContainsString('users', $infoDescription);

        $concreteCollectionParameters = $response->json('paths./api/crud/users.get.parameters') ?? [];
        $this->assertNotContains('resource', array_column($concreteCollectionParameters, 'name'));

        $concreteItemParameters = $response->json('paths./api/crud/users/{id}.get.parameters') ?? [];
        $this->assertContains('id', array_column($concreteItemParameters, 'name'));
        $this->assertNotContains('resource', array_column($concreteItemParameters, 'name'));

        $this->assertArrayNotHasKey('/api/session/ping', $response->json('paths'));
        $this->assertArrayNotHasKey('post', $response->json('paths./api/tokens') ?? []);
        $this->assertArrayNotHasKey('post', $response->json('paths./api/admin/api-tokens') ?? []);
    }

    public function test_swagger_ui_page_is_available(): void
    {
        $response = $this->get('/api/docs');

        $response->assertOk();
        $response->assertSee('swagger-ui', false);
        $response->assertSee('/openapi.json', false);
    }
}


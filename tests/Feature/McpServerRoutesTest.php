<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class McpServerRoutesTest extends TestCase
{
    public function test_mcp_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('mcp.streamable.get'));
        $this->assertTrue(Route::has('mcp.streamable.post'));
        $this->assertTrue(Route::has('mcp.streamable.delete'));
    }

    public function test_guest_cannot_access_mcp_endpoint(): void
    {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => new \stdClass(),
        ]);

        $response->assertUnauthorized();
    }
}


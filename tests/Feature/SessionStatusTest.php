<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('enabled')->default(true);
                $table->string('remember_token', 100)->nullable();
                $table->string('external_id')->nullable();
                $table->string('title')->nullable();
                $table->unsignedBigInteger('manager_user_id')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_session_authenticated_user_can_ping_session(): void
    {
        $this->actingAs($this->createUser('Session User', 'session@example.com', true));

        $response = $this->getJson('/api/session/ping');

        $response->assertOk();
        $response->assertJsonPath('authenticated', true);
    }

    public function test_guest_is_unauthenticated_for_ping_route(): void
    {
        $response = $this->getJson('/api/session/ping');

        $response->assertUnauthorized();
    }

    public function test_token_authenticated_user_is_rejected_for_ping_route(): void
    {
        $user = $this->createUser('Token User', 'token@example.com', true);
        $token = $user->createToken('api-test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/session/ping');

        $response->assertForbidden();
        $response->assertJsonPath('message', __('api.errors.session_auth_required'));
    }

    private function createUser(string $name, string $email, bool $enabled): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'enabled' => $enabled,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }
}

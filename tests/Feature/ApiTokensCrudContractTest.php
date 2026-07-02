<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTokensCrudContractTest extends TestCase
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
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('access_groups')) {
            Schema::create('access_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->json('claims')->nullable();
                $table->unsignedBigInteger('risk_level_id')->nullable();
                $table->unsignedBigInteger('external_provider_group_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('access_group_user')) {
            Schema::create('access_group_user', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('access_group_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('personal_access_tokens')->truncate();
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_api_tokens_index_is_forbidden_without_permissions(): void
    {
        $this->actingAs($this->createUser('Unauthorized', 'unauthorized.tokens@example.com'), 'sanctum');

        $response = $this->getJson('/api/admin/api-tokens');

        $response->assertForbidden();
    }

    public function test_api_tokens_crud_returns_plain_text_token_on_create_and_supports_update_delete(): void
    {
        $admin = $this->createUser('Token Admin', 'token.admin@example.com');
        $subject = $this->createUser('Token Subject', 'token.subject@example.com');

        $this->grantClaims($admin, ['systemadministrator.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $createResponse = $this->postJson('/api/admin/api-tokens', [
            'name' => 'Integration token',
            'user_id' => $subject->id,
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('name', 'Integration token');
        $createResponse->assertJsonPath('user_id', $subject->id);
        $createResponse->assertJsonStructure(['id', 'name', 'user_id', 'plain_text_token']);

        $tokenId = (int) $createResponse->json('id');

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'name' => 'Integration token',
            'tokenable_id' => $subject->id,
            'tokenable_type' => User::class,
        ]);

        $indexResponse = $this->getJson('/api/admin/api-tokens?paginate=0&search=Integration&sort=-created_at');

        $indexResponse->assertOk();
        $rows = $indexResponse->json();
        $this->assertCount(1, $rows);
        $this->assertSame($tokenId, $rows[0]['id']);

        $updateResponse = $this->patchJson('/api/admin/api-tokens/'.$tokenId, [
            'name' => 'Integration token updated',
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('name', 'Integration token updated');

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'name' => 'Integration token updated',
        ]);

        $deleteResponse = $this->deleteJson('/api/admin/api-tokens/'.$tokenId);
        $deleteResponse->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    private function createUser(string $name, string $email): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    /**
     * @param array<int, string> $claims
     */
    private function grantClaims(User $user, array $claims): void
    {
        $groupId = DB::table('access_groups')->insertGetId([
            'name' => 'Token permission group '.Str::lower((string) Str::uuid()),
            'claims' => json_encode($claims, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('access_group_user')->insert([
            'access_group_id' => $groupId,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $user->id)->update([
            'updated_at' => now(),
        ]);
    }
}


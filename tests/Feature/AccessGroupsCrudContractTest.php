<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessGroupsCrudContractTest extends TestCase
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

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_access_groups_index_is_forbidden_without_permissions(): void
    {
        $this->actingAs($this->createUser('Unauthorized', 'unauthorized@example.com'), 'sanctum');

        $response = $this->getJson('/api/crud/access-groups');

        $response->assertForbidden();
    }

    public function test_access_groups_crud_supports_user_ids_and_claims_payload(): void
    {
        $admin = $this->createUser('Access Admin', 'access.admin@example.com');
        $this->grantClaims($admin, ['systemadministrator.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $memberOne = $this->createUser('Member One', 'member.one@example.com');
        $memberTwo = $this->createUser('Member Two', 'member.two@example.com');

        $createResponse = $this->postJson('/api/crud/access-groups', [
            'name' => 'Contract Group',
            'claims' => ['systemadministrator.edit', 'superadmin.edit'],
            'user_ids' => [$memberOne->id, $memberTwo->id],
        ]);

        $createResponse->assertCreated();
        $groupId = (int) $createResponse->json('id');

        $this->assertDatabaseHas('access_groups', [
            'id' => $groupId,
            'name' => 'Contract Group',
        ]);

        $this->assertDatabaseHas('access_group_user', [
            'access_group_id' => $groupId,
            'user_id' => $memberOne->id,
        ]);
        $this->assertDatabaseHas('access_group_user', [
            'access_group_id' => $groupId,
            'user_id' => $memberTwo->id,
        ]);

        $patchResponse = $this->patchJson('/api/crud/access-groups/'.$groupId, [
            'claims' => ['systemadministrator.edit'],
            'user_ids' => [$memberTwo->id],
        ]);

        $patchResponse->assertOk();
        $patchResponse->assertJsonPath('claims.0', 'systemadministrator.edit');

        $this->assertDatabaseMissing('access_group_user', [
            'access_group_id' => $groupId,
            'user_id' => $memberOne->id,
        ]);
        $this->assertDatabaseHas('access_group_user', [
            'access_group_id' => $groupId,
            'user_id' => $memberTwo->id,
        ]);

        $indexResponse = $this->getJson('/api/crud/access-groups?paginate=0&search=Contract%20Group&%24select=id,name,claims');

        $indexResponse->assertOk();
        $rows = $indexResponse->json();

        $this->assertCount(1, $rows);
        $this->assertSame([$memberTwo->id], $rows[0]['user_ids']);
        $this->assertSame(['systemadministrator.edit'], $rows[0]['claims']);
    }

    public function test_access_group_claim_options_endpoint_returns_catalog(): void
    {
        $admin = $this->createUser('Access Catalog Admin', 'access.catalog.admin@example.com');
        $this->grantClaims($admin, ['systemadministrator.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $response = $this->getJson('/api/access-groups/claims');

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'systemadministrator.edit']);
        $response->assertJsonFragment(['id' => 'superadmin.edit']);
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
            'name' => 'Permission Group '.Str::lower((string) Str::uuid()),
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


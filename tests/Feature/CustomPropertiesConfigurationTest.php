<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomPropertiesConfigurationTest extends TestCase
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

        if (! Schema::hasTable('custom_properties')) {
            Schema::create('custom_properties', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->string('context');
                $table->string('type', 20);
                $table->text('options')->nullable();
                $table->bigInteger('ordinal')->default(0);
                $table->boolean('display_on_card')->default(false);
                $table->boolean('user_editable')->default(true);
                $table->boolean('required')->default(false);
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('custom_properties')->truncate();
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_custom_property_contexts_are_forbidden_without_permissions(): void
    {
        $this->actingAs($this->createUser('Unauthorized', 'unauthorized.custom.properties@example.com'), 'sanctum');

        $response = $this->getJson('/api/custom-properties/contexts');

        $response->assertForbidden();
    }

    public function test_custom_property_context_catalog_and_scoped_crud_flow(): void
    {
        $admin = $this->createUser('Custom Property Admin', 'custom.property.admin@example.com');
        $this->grantClaims($admin, ['systemadministrator.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $contextsResponse = $this->getJson('/api/custom-properties/contexts');

        $contextsResponse->assertOk();
        $contextsResponse->assertJsonStructure(['data' => [['resource', 'context', 'label']]]);
        $contextsResponse->assertJsonFragment([
            'resource' => 'users',
            'context' => 'App\\Models\\User',
        ]);

        $contexts = collect($contextsResponse->json('data'));
        $this->assertSame(
            $contexts->count(),
            $contexts->pluck('context')->unique()->count(),
            'Custom property contexts should be unique by context model class.'
        );

        $userContext = 'App\\Models\\User';
        $departmentContext = 'App\\Models\\Department';

        DB::table('custom_properties')->insert([
            [
                'name' => 'User Existing',
                'description' => 'User description',
                'context' => $userContext,
                'type' => 'string',
                'options' => null,
                'ordinal' => 1,
                'display_on_card' => true,
                'user_editable' => true,
                'required' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Department Existing',
                'description' => 'Department description',
                'context' => $departmentContext,
                'type' => 'string',
                'options' => null,
                'ordinal' => 1,
                'display_on_card' => false,
                'user_editable' => true,
                'required' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $encodedUserContext = rawurlencode($userContext);

        $indexResponse = $this->getJson('/api/custom-properties/contexts/'.$encodedUserContext.'?paginate=0&%24select=id,name,context');

        $indexResponse->assertOk();
        $rows = $indexResponse->json();
        $this->assertCount(1, $rows);
        $this->assertSame('User Existing', $rows[0]['name']);
        $this->assertSame($userContext, $rows[0]['context']);

        $createResponse = $this->postJson('/api/custom-properties/contexts/'.$encodedUserContext, [
            'name' => 'Employment type',
            'description' => 'Custom value for users',
            'type' => 'string',
            'display_on_card' => true,
            'user_editable' => true,
            'required' => false,
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('context', $userContext);
        $createResponse->assertJsonPath('name', 'Employment type');

        $createdId = (int) $createResponse->json('id');

        $updateResponse = $this->patchJson('/api/custom-properties/contexts/'.$encodedUserContext.'/'.$createdId, [
            'name' => 'Employment classification',
            'context' => $departmentContext,
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('name', 'Employment classification');
        $updateResponse->assertJsonPath('context', $userContext);

        $deleteResponse = $this->deleteJson('/api/custom-properties/contexts/'.$encodedUserContext.'/'.$createdId);
        $deleteResponse->assertNoContent();

        $this->assertDatabaseMissing('custom_properties', [
            'id' => $createdId,
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
            'name' => 'Custom property permission group '.Str::lower((string) Str::uuid()),
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


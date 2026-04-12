<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityFlowTemplatesCrudContractTest extends TestCase
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

        if (! Schema::hasTable('activity_flow_templates')) {
            Schema::create('activity_flow_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('user_instantiatable')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('activity_flow_template_items')) {
            Schema::create('activity_flow_template_items', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('type')->default('item');
                $table->unsignedInteger('ordinal')->default(0);
                $table->text('description')->nullable();
                $table->boolean('waitforpreceeding')->default(false);
                $table->integer('dueoffsetdays')->default(0);
                $table->unsignedBigInteger('activity_flow_template_id');
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('activity_flow_template_items')->truncate();
        DB::table('activity_flow_templates')->truncate();
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_activity_flow_templates_index_is_forbidden_without_permissions(): void
    {
        $this->actingAs($this->createUser('Unauthorized', 'aft.unauthorized@example.com'), 'sanctum');

        $response = $this->getJson('/api/crud/activity-flow-templates');

        $response->assertForbidden();
    }

    public function test_activity_flow_templates_resource_is_discoverable_in_catalog(): void
    {
        $admin = $this->createUser('Catalog Admin', 'aft.catalog.admin@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $response = $this->getJson('/api/crud/resources');

        $response->assertOk();
        $resources = array_column($response->json('data'), 'resource');
        $this->assertContains('activity-flow-templates', $resources);
    }

    public function test_activity_flow_templates_crud_supports_create_update_index_and_delete(): void
    {
        $admin = $this->createUser('Template Admin', 'aft.admin@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $createResponse = $this->postJson('/api/crud/activity-flow-templates', [
            'name' => 'Annual Review Template',
            'description' => 'Template for annual activity planning.',
            'user_instantiatable' => true,
        ]);

        $createResponse->assertCreated();
        $templateId = (int) $createResponse->json('id');

        $this->assertDatabaseHas('activity_flow_templates', [
            'id' => $templateId,
            'name' => 'Annual Review Template',
            'user_instantiatable' => 1,
        ]);

        $this->patchJson('/api/crud/activity-flow-templates/'.$templateId, [
            'description' => 'Updated template for annual activity planning.',
            'user_instantiatable' => false,
        ])->assertOk()
            ->assertJsonPath('user_instantiatable', false);

        $this->getJson('/api/crud/activity-flow-templates?paginate=0&search=Annual&%24select=id,name,user_instantiatable')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $templateId)
            ->assertJsonPath('0.user_instantiatable', false);

        $this->deleteJson('/api/crud/activity-flow-templates/'.$templateId)->assertNoContent();

        $this->assertDatabaseMissing('activity_flow_templates', [
            'id' => $templateId,
        ]);
    }

    public function test_activity_flow_template_items_can_be_filtered_and_sorted_by_template_and_ordinal(): void
    {
        $admin = $this->createUser('Template Items Admin', 'aft.items.admin@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $templateAId = DB::table('activity_flow_templates')->insertGetId([
            'name' => 'Template A',
            'description' => 'A',
            'user_instantiatable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateBId = DB::table('activity_flow_templates')->insertGetId([
            'name' => 'Template B',
            'description' => 'B',
            'user_instantiatable' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('activity_flow_template_items')->insert([
            'name' => 'Start Step',
            'type' => 'header',
            'ordinal' => 1,
            'description' => 'First step',
            'waitforpreceeding' => false,
            'dueoffsetdays' => 0,
            'activity_flow_template_id' => $templateAId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->postJson('/api/crud/activity-flow-template-items', [
            'name' => 'Review Step',
            'type' => 'item',
            'description' => 'Second step',
            'waitforpreceeding' => true,
            'dueoffsetdays' => 5,
            'activity_flow_template_id' => $templateAId,
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('ordinal', 2);
        $itemId = (int) $createResponse->json('id');

        DB::table('activity_flow_template_items')->insert([
            'name' => 'Other Template Step',
            'type' => 'item',
            'ordinal' => 1,
            'description' => 'Other template',
            'waitforpreceeding' => false,
            'dueoffsetdays' => 1,
            'activity_flow_template_id' => $templateBId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $filteredResponse = $this->getJson('/api/crud/activity-flow-template-items?paginate=0&filter[activity_flow_template_id]='.$templateAId.'&sort=ordinal&%24select=id,name,ordinal,activity_flow_template_id');

        $filteredResponse->assertOk()->assertJsonCount(2);
        $filteredResponse->assertJsonPath('0.name', 'Start Step');
        $filteredResponse->assertJsonPath('1.name', 'Review Step');
        $filteredResponse->assertJsonPath('0.activity_flow_template_id', $templateAId);
        $filteredResponse->assertJsonPath('1.activity_flow_template_id', $templateAId);

        $this->patchJson('/api/crud/activity-flow-template-items/'.$itemId, [
            'ordinal' => 3,
        ])->assertOk()->assertJsonPath('ordinal', 3);

        $this->deleteJson('/api/crud/activity-flow-template-items/'.$itemId)->assertNoContent();
        $this->assertDatabaseMissing('activity_flow_template_items', ['id' => $itemId]);
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


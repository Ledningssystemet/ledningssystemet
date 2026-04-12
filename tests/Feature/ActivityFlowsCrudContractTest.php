<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityFlowsCrudContractTest extends TestCase
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

        if (! Schema::hasTable('activity_flows')) {
            Schema::create('activity_flows', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('responsible_user_id');
                $table->unsignedBigInteger('activity_flow_template_id');
                $table->dateTime('started_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('activities')) {
            Schema::create('activities', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->date('due');
                $table->unsignedInteger('intervalnum');
                $table->string('intervaltype')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->unsignedBigInteger('activity_flow_id')->nullable();
                $table->unsignedBigInteger('activity_flow_template_item_id')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('activities')->truncate();
        DB::table('activity_flows')->truncate();
        DB::table('activity_flow_template_items')->truncate();
        DB::table('activity_flow_templates')->truncate();
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_activity_flows_index_is_available_for_authenticated_users(): void
    {
        $this->actingAs($this->createUser('Unauthorized', 'af.unauthorized@example.com'), 'sanctum');

        $response = $this->getJson('/api/crud/activity-flows');

        $response->assertOk();
    }

    public function test_activity_flows_resource_is_discoverable_in_catalog(): void
    {
        $admin = $this->createUser('Catalog Admin', 'af.catalog.admin@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $response = $this->getJson('/api/crud/resources');

        $response->assertOk();
        $resources = array_column($response->json('data'), 'resource');
        $this->assertContains('activity-flows', $resources);
    }

    public function test_creating_activity_flow_generates_activities_from_item_template_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-11 08:00:00'));

        $admin = $this->createUser('Flow Admin', 'af.admin@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $templateId = DB::table('activity_flow_templates')->insertGetId([
            'name' => 'Quarterly Compliance Flow',
            'description' => 'Template for quarterly follow-up.',
            'user_instantiatable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('activity_flow_template_items')->insert([
            [
                'name' => 'Preparation Header',
                'type' => 'header',
                'ordinal' => 1,
                'description' => 'Header only',
                'waitforpreceeding' => false,
                'dueoffsetdays' => 0,
                'activity_flow_template_id' => $templateId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Collect Input',
                'type' => 'item',
                'ordinal' => 2,
                'description' => 'Collect data from departments.',
                'waitforpreceeding' => false,
                'dueoffsetdays' => 3,
                'activity_flow_template_id' => $templateId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sign-off',
                'type' => 'item',
                'ordinal' => 3,
                'description' => null,
                'waitforpreceeding' => true,
                'dueoffsetdays' => 10,
                'activity_flow_template_id' => $templateId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $createResponse = $this->postJson('/api/crud/activity-flows', [
            'name' => 'Q2 Compliance 2026',
            'description' => 'Generated from template.',
            'responsible_user_id' => $admin->id,
            'activity_flow_template_id' => $templateId,
            'started_at' => '2026-04-20',
        ]);

        $createResponse->assertCreated();
        $flowId = (int) $createResponse->json('id');

        $this->assertDatabaseHas('activity_flows', [
            'id' => $flowId,
            'name' => 'Q2 Compliance 2026',
        ]);

        $generated = DB::table('activities')
            ->where('activity_flow_id', $flowId)
            ->orderBy('activity_flow_template_item_id')
            ->get();

        $this->assertCount(2, $generated, 'Only template rows of type=item should generate activities.');
        $this->assertSame('Collect Input', $generated[0]->name);
        $this->assertSame('2026-04-23', $generated[0]->due);
        $this->assertSame('Sign-off', $generated[1]->name);
        $this->assertSame('Sign-off', $generated[1]->description, 'Missing template description should fallback to item name.');
        $this->assertSame('2026-04-30', $generated[1]->due);

        Carbon::setTestNow();
    }

    public function test_creating_activity_flow_with_template_without_item_rows_generates_no_activities(): void
    {
        $admin = $this->createUser('Flow Admin 2', 'af.admin2@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $templateId = DB::table('activity_flow_templates')->insertGetId([
            'name' => 'Header Only Flow',
            'description' => 'Contains no item rows.',
            'user_instantiatable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('activity_flow_template_items')->insert([
            'name' => 'Header Row',
            'type' => 'header',
            'ordinal' => 1,
            'description' => 'No concrete activity.',
            'waitforpreceeding' => false,
            'dueoffsetdays' => 0,
            'activity_flow_template_id' => $templateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->postJson('/api/crud/activity-flows', [
            'name' => 'Header Flow Instance',
            'description' => 'Should not generate items.',
            'responsible_user_id' => $admin->id,
            'activity_flow_template_id' => $templateId,
        ]);

        $createResponse->assertCreated();
        $flowId = (int) $createResponse->json('id');

        $this->assertDatabaseCount('activities', 0);
        $this->assertDatabaseHas('activity_flows', ['id' => $flowId]);
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


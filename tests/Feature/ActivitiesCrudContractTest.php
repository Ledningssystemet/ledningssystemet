<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivitiesCrudContractTest extends TestCase
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

        if (! Schema::hasTable('activities')) {
            Schema::create('activities', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->date('due');
                $table->unsignedInteger('intervalnum')->default(0);
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
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_activities_resource_is_discoverable_in_crud_catalog(): void
    {
        $this->actingAs($this->createUser('Catalog User', 'activities.catalog@example.com'), 'sanctum');

        $response = $this->getJson('/api/crud/resources');

        $response->assertOk();
        $resources = array_column($response->json('data'), 'resource');
        $this->assertContains('activities', $resources);
    }

    public function test_activities_crud_supports_create_update_index_and_delete_for_responsible_user(): void
    {
        $user = $this->createUser('Activity Owner', 'activity.owner@example.com');
        $this->actingAs($user, 'sanctum');

        $createResponse = $this->postJson('/api/crud/activities', [
            'name' => 'Weekly follow-up',
            'description' => 'Follow up ongoing control action.',
            'due' => '2026-05-01',
            'intervalnum' => 7,
            'intervaltype' => 'day',
            'responsible_user_id' => $user->id,
        ]);

        $createResponse->assertCreated();
        $activityId = (int) $createResponse->json('id');
        $this->assertDatabaseHas('activities', [
            'id' => $activityId,
            'name' => 'Weekly follow-up',
            'responsible_user_id' => $user->id,
        ]);

        $this->patchJson('/api/crud/activities/'.$activityId, [
            'description' => 'Follow up and summarize status.',
            'intervalnum' => 14,
        ])->assertOk()
            ->assertJsonPath('intervalnum', 14);

        $this->getJson('/api/crud/activities?paginate=0&search=Weekly&%24select=id,name,due,intervalnum,responsible_user_id')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $activityId)
            ->assertJsonPath('0.intervalnum', 14);

        $this->deleteJson('/api/crud/activities/'.$activityId)->assertNoContent();
        $this->assertDatabaseMissing('activities', ['id' => $activityId]);
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
}


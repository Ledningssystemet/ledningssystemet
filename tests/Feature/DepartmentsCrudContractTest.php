<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepartmentsCrudContractTest extends TestCase
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

        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('external_provider_group_id')->nullable();
                $table->unsignedBigInteger('parent_department_id')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('department_user')) {
            Schema::create('department_user', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('department_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('processes')) {
            Schema::create('processes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('risks')) {
            Schema::create('risks', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('findings')) {
            Schema::create('findings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('findings')->truncate();
        DB::table('risks')->truncate();
        DB::table('processes')->truncate();
        DB::table('department_user')->truncate();
        DB::table('departments')->truncate();
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_departments_crud_supports_user_ids_and_usage_fields(): void
    {
        $admin = $this->createUser('Department Admin', 'department.admin@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $member = $this->createUser('Department Member', 'department.member@example.com');

        $createResponse = $this->postJson('/api/crud/departments', [
            'name' => 'Operations',
            'user_ids' => [$member->id],
        ]);

        $createResponse->assertCreated();
        $departmentId = (int) $createResponse->json('id');

        DB::table('processes')->insert([
            'name' => 'Department Process',
            'department_id' => $departmentId,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indexResponse = $this->getJson('/api/crud/departments?paginate=0&search=Operations&%24select=id,name');

        $indexResponse->assertOk();
        $rows = $indexResponse->json();

        $this->assertCount(1, $rows);
        $this->assertSame([$member->id], $rows[0]['user_ids']);
        $this->assertSame(1, $rows[0]['processcount']);
        $this->assertSame(0, $rows[0]['departmentriskcount']);
        $this->assertSame(0, $rows[0]['departmentfindingcount']);
        $this->assertFalse($rows[0]['can_delete']);
    }

    public function test_departments_reassign_endpoint_moves_related_objects(): void
    {
        $admin = $this->createUser('Department Reassign Admin', 'department.reassign.admin@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $sourceId = DB::table('departments')->insertGetId([
            'name' => 'Source Department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetId = DB::table('departments')->insertGetId([
            'name' => 'Target Department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processId = DB::table('processes')->insertGetId([
            'name' => 'Source Process',
            'department_id' => $sourceId,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $riskId = DB::table('risks')->insertGetId([
            'name' => 'Source Risk',
            'department_id' => $sourceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $findingId = DB::table('findings')->insertGetId([
            'name' => 'Source Finding',
            'description' => 'Test finding',
            'department_id' => $sourceId,
            'nonconformity' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/departments/'.$sourceId.'/reassign', [
            'processes' => $targetId,
            'risks' => $targetId,
            'findings' => $targetId,
        ]);

        $response->assertOk();
        $response->assertJsonPath('moved.processes', 1);
        $response->assertJsonPath('moved.risks', 1);
        $response->assertJsonPath('moved.findings', 1);

        $this->assertDatabaseHas('processes', [
            'id' => $processId,
            'department_id' => $targetId,
        ]);
        $this->assertDatabaseHas('risks', [
            'id' => $riskId,
            'department_id' => $targetId,
        ]);
        $this->assertDatabaseHas('findings', [
            'id' => $findingId,
            'department_id' => $targetId,
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


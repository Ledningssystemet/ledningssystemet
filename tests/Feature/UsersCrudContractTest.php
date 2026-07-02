<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsersCrudContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('enabled')->default(true);
                $table->string('remember_token', 100)->nullable();
                $table->string('external_id')->nullable()->unique();
                $table->string('title')->nullable();
                $table->unsignedBigInteger('manager_user_id')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->timestamp('last_login_at')->nullable();
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

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->text('authorities')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }

        foreach ([
            'activities' => 'responsible_user_id',
            'assets' => 'responsible_user_id',
            'controls' => 'responsible_user_id',
            'incidents' => 'responsible_user_id',
            'information_types' => 'responsible_user_id',
            'objectives' => 'responsible_user_id',
            'processes' => 'responsible_user_id',
            'process_performance_metrics' => 'responsible_user_id',
            'suppliers' => 'responsible_user_id',
        ] as $tableName => $userColumn) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table) use ($userColumn): void {
                    $table->id();
                    $table->string('name')->nullable();
                    $table->unsignedBigInteger($userColumn)->nullable();
                    $table->timestamps();
                });
            }
        }

        if (! Schema::hasTable('control_actions')) {
            Schema::create('control_actions', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->unsignedBigInteger('responsible_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('risks')) {
            Schema::create('risks', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->unsignedBigInteger('riskowner_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'risks',
            'control_actions',
            'suppliers',
            'process_performance_metrics',
            'processes',
            'objectives',
            'information_types',
            'incidents',
            'controls',
            'assets',
            'activities',
            'role_user',
            'roles',
            'department_user',
            'departments',
            'access_group_user',
            'access_groups',
            'users',
        ] as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_users_crud_supports_assignment_fields_counts_and_department_search(): void
    {
        $admin = $this->createUser('User Admin', 'user.admin@example.com');
        $this->grantClaims($admin, ['systemadministrator.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $manager = $this->createUser('Line Manager', 'line.manager@example.com');
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Operations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Coordinator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $membershipAccessGroupId = DB::table('access_groups')->insertGetId([
            'name' => 'Operations Editors',
            'claims' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->postJson('/api/crud/users', [
            'name' => 'Managed User',
            'email' => 'managed.user@example.com',
            'enabled' => true,
            'title' => 'Coordinator',
            'manager_user_id' => $manager->id,
            'departments' => [$departmentId],
            'roles' => [$roleId],
            'accessgroups' => [$membershipAccessGroupId],
        ]);

        $createResponse->assertCreated();
        $userId = (int) $createResponse->json('id');

        $this->assertDatabaseHas('department_user', [
            'department_id' => $departmentId,
            'user_id' => $userId,
        ]);
        $this->assertDatabaseHas('role_user', [
            'role_id' => $roleId,
            'user_id' => $userId,
        ]);
        $this->assertDatabaseHas('access_group_user', [
            'access_group_id' => $membershipAccessGroupId,
            'user_id' => $userId,
        ]);

        $createdRow = DB::table('users')->where('id', $userId)->first();
        $this->assertNotNull($createdRow);
        $this->assertTrue(is_string($createdRow->password) && $createdRow->password !== '');

        DB::table('users')->insert([
            'name' => 'Direct Report',
            'email' => 'direct.report@example.com',
            'password' => Hash::make('password'),
            'enabled' => true,
            'manager_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertTableRecord('processes', [
            'name' => 'Managed Process',
            'description' => 'Managed process description',
            'department_id' => $departmentId,
            'responsible_user_id' => $userId,
            'isstartprocess' => false,
            'dataprocessor' => false,
        ]);

        $indexResponse = $this->getJson('/api/crud/users?paginate=0&search=Operations&%24select=id,name,email,departments,roles,accessgroups,direct_reports,processescount,can_delete');

        $indexResponse->assertOk();
        $rows = $indexResponse->json();

        $this->assertCount(1, $rows);
        $this->assertSame([$departmentId], $rows[0]['departments']);
        $this->assertSame([$roleId], $rows[0]['roles']);
        $this->assertSame([$membershipAccessGroupId], $rows[0]['accessgroups']);
        $this->assertSame('Direct Report', $rows[0]['direct_reports'][0]['name']);
        $this->assertSame(1, $rows[0]['processescount']);
        $this->assertFalse($rows[0]['can_delete']);
    }

    public function test_users_reassign_endpoint_moves_related_objects(): void
    {
        $admin = $this->createUser('User Reassign Admin', 'user.reassign.admin@example.com');
        $this->grantClaims($admin, ['systemadministrator.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $source = $this->createUser('Source User', 'source.user@example.com');
        $target = $this->createUser('Target User', 'target.user@example.com');

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Reassign Department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controlId = $this->insertTableRecord('controls', [
            'name' => 'Control',
            'description' => 'Control description',
            'responsible_user_id' => $source->id,
        ]);

        $this->insertTableRecord('activities', [
            'name' => 'Activity',
            'description' => 'Activity description',
            'due' => now()->toDateString(),
            'responsible_user_id' => $source->id,
        ]);
        $this->insertTableRecord('assets', [
            'name' => 'Asset',
            'description' => 'Asset description',
            'responsible_user_id' => $source->id,
        ]);
        $this->insertTableRecord('control_actions', [
            'name' => 'Control action',
            'description' => 'Control action description',
            'control_id' => $controlId,
            'responsible_id' => $source->id,
        ]);
        $this->insertTableRecord('incidents', [
            'name' => 'Incident',
            'eventdescription' => 'Incident event description',
            'started_at' => now(),
            'responsible_user_id' => $source->id,
        ]);
        $this->insertTableRecord('information_types', [
            'name' => 'Information type',
            'description' => 'Information type description',
            'responsible_user_id' => $source->id,
        ]);
        $this->insertTableRecord('objectives', [
            'name' => 'Objective',
            'description' => 'Objective description',
            'responsible_user_id' => $source->id,
        ]);
        $this->insertTableRecord('processes', [
            'name' => 'Process',
            'description' => 'Process description',
            'department_id' => $departmentId,
            'responsible_user_id' => $source->id,
            'isstartprocess' => false,
            'dataprocessor' => false,
        ]);
        $this->insertTableRecord('process_performance_metrics', [
            'name' => 'Metric',
            'description' => 'Metric description',
            'responsible_user_id' => $source->id,
            'quantitative' => true,
            'biggerisbetter' => true,
        ]);
        $this->insertTableRecord('risks', [
            'name' => 'Risk',
            'department_id' => $departmentId,
            'riskowner_id' => $source->id,
        ]);
        $this->insertTableRecord('suppliers', [
            'name' => 'Supplier',
            'description' => 'Supplier description',
            'responsible_user_id' => $source->id,
        ]);

        $response = $this->postJson('/api/users/'.$source->id.'/reassign', [
            'activities' => $target->id,
            'assets' => $target->id,
            'controls' => $target->id,
            'control_actions' => $target->id,
            'incidents' => $target->id,
            'information_types' => $target->id,
            'objectives' => $target->id,
            'processes' => $target->id,
            'process_performance_metrics' => $target->id,
            'risks' => $target->id,
            'suppliers' => $target->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('moved.activities', 1);
        $response->assertJsonPath('moved.assets', 1);
        $response->assertJsonPath('moved.controls', 1);
        $response->assertJsonPath('moved.control_actions', 1);
        $response->assertJsonPath('moved.incidents', 1);
        $response->assertJsonPath('moved.information_types', 1);
        $response->assertJsonPath('moved.objectives', 1);
        $response->assertJsonPath('moved.processes', 1);
        $response->assertJsonPath('moved.process_performance_metrics', 1);
        $response->assertJsonPath('moved.risks', 1);
        $response->assertJsonPath('moved.suppliers', 1);

        $this->assertDatabaseHas('activities', ['responsible_user_id' => $target->id]);
        $this->assertDatabaseHas('assets', ['responsible_user_id' => $target->id]);
        $this->assertDatabaseHas('controls', ['responsible_user_id' => $target->id]);
        $this->assertDatabaseHas('control_actions', ['responsible_id' => $target->id]);
        $this->assertDatabaseHas('incidents', ['responsible_user_id' => $target->id]);
        $this->assertDatabaseHas('information_types', ['responsible_user_id' => $target->id]);
        $this->assertDatabaseHas('objectives', ['responsible_user_id' => $target->id]);
        $this->assertDatabaseHas('processes', ['responsible_user_id' => $target->id]);
        $this->assertDatabaseHas('process_performance_metrics', ['responsible_user_id' => $target->id]);
        $this->assertDatabaseHas('risks', ['riskowner_id' => $target->id]);
        $this->assertDatabaseHas('suppliers', ['responsible_user_id' => $target->id]);
    }

    public function test_users_password_reset_endpoint_sends_reset_link_for_local_users(): void
    {
        $admin = $this->createUser('User Password Admin', 'user.password.admin@example.com');
        $this->grantClaims($admin, ['systemadministrator.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $user = $this->createUser('Resettable User', 'resettable.user@example.com');

        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'resettable.user@example.com'])
            ->andReturn(Password::RESET_LINK_SENT);

        $response = $this->postJson('/api/users/'.$user->id.'/password-reset');

        $response->assertOk();
        $response->assertJsonPath('status', 'sent');
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

    private function insertTableRecord(string $table, array $values): int
    {
        $columns = array_flip(Schema::getColumnListing($table));
        $payload = [];

        foreach ($values as $column => $value) {
            if (isset($columns[$column])) {
                $payload[$column] = $value;
            }
        }

        if (isset($columns['created_at']) && ! array_key_exists('created_at', $payload)) {
            $payload['created_at'] = now();
        }
        if (isset($columns['updated_at']) && ! array_key_exists('updated_at', $payload)) {
            $payload['updated_at'] = now();
        }

        return (int) DB::table($table)->insertGetId($payload);
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


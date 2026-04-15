<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contract tests for the EmployeeProfileController endpoints.
 *
 * Covers: GET /api/employees/{userId}
 *         GET /api/employees/{userId}/roles
 *         GET /api/employees/{userId}/qualifications
 *         GET /api/employees/{userId}/competences
 *         GET /api/employees/{userId}/responsibilities
 */
class EmployeesProfileContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'users' => function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('enabled')->default(true);
                $table->string('title')->nullable();
                $table->unsignedBigInteger('manager_user_id')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamps();
            },
            'access_groups' => function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->json('claims')->nullable();
                $table->unsignedBigInteger('risk_level_id')->nullable();
                $table->unsignedBigInteger('external_provider_group_id')->nullable();
                $table->timestamps();
            },
            'access_group_user' => function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('access_group_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            },
            'roles' => function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->text('authorities')->nullable();
                $table->timestamps();
            },
            'role_user' => function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            },
        ] as $tableName => $blueprint) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, $blueprint);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('role_user')->truncate();
        DB::table('roles')->truncate();
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    // ─── Authorization ────────────────────────────────────────────────────────

    public function test_show_returns_403_without_managementtools_edit(): void
    {
        $actor   = $this->createUser('Actor', 'emp.actor@example.com');
        $subject = $this->createUser('Subject', 'emp.subject@example.com');
        $this->actingAs($actor, 'sanctum');

        $this->getJson("/api/employees/{$subject->id}")->assertForbidden();
    }

    public function test_roles_returns_403_without_managementtools_edit(): void
    {
        $actor   = $this->createUser('Actor', 'emp.actor2@example.com');
        $subject = $this->createUser('Subject', 'emp.subject2@example.com');
        $this->actingAs($actor, 'sanctum');

        $this->getJson("/api/employees/{$subject->id}/roles")->assertForbidden();
    }

    public function test_qualifications_returns_403_without_managementtools_edit(): void
    {
        $actor   = $this->createUser('Actor', 'emp.actor3@example.com');
        $subject = $this->createUser('Subject', 'emp.subject3@example.com');
        $this->actingAs($actor, 'sanctum');

        $this->getJson("/api/employees/{$subject->id}/qualifications")->assertForbidden();
    }

    public function test_competences_returns_403_without_managementtools_edit(): void
    {
        $actor   = $this->createUser('Actor', 'emp.actor4@example.com');
        $subject = $this->createUser('Subject', 'emp.subject4@example.com');
        $this->actingAs($actor, 'sanctum');

        $this->getJson("/api/employees/{$subject->id}/competences")->assertForbidden();
    }

    public function test_responsibilities_returns_403_without_managementtools_edit(): void
    {
        $actor   = $this->createUser('Actor', 'emp.actor5@example.com');
        $subject = $this->createUser('Subject', 'emp.subject5@example.com');
        $this->actingAs($actor, 'sanctum');

        $this->getJson("/api/employees/{$subject->id}/responsibilities")->assertForbidden();
    }

    // ─── 404 ─────────────────────────────────────────────────────────────────

    public function test_show_returns_404_for_unknown_user(): void
    {
        $actor = $this->createUser('Admin', 'emp.admin404@example.com');
        $this->grantClaims($actor, ['managementtools.edit']);
        $this->actingAs($actor->fresh(), 'sanctum');

        $this->getJson('/api/employees/999999')->assertNotFound();
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_show_returns_employee_profile(): void
    {
        $admin   = $this->createUser('Admin', 'emp.admin.show@example.com');
        $subject = $this->createUser('Jane Doe', 'emp.jane@example.com', 'Senior Developer');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $response = $this->getJson("/api/employees/{$subject->id}");

        $response->assertOk()
            ->assertJsonPath('id', $subject->id)
            ->assertJsonPath('name', 'Jane Doe')
            ->assertJsonPath('email', 'emp.jane@example.com')
            ->assertJsonPath('title', 'Senior Developer')
            ->assertJsonStructure(['id', 'name', 'email', 'title', 'enabled', 'manager', 'departments', 'direct_reports']);
    }

    public function test_roles_returns_array_for_employee(): void
    {
        $admin   = $this->createUser('Admin', 'emp.admin.roles@example.com');
        $subject = $this->createUser('John Doe', 'emp.john.roles@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $response = $this->getJson("/api/employees/{$subject->id}/roles");

        $response->assertOk()->assertJsonIsArray();
    }

    public function test_qualifications_returns_achieved_and_missing_keys(): void
    {
        $admin   = $this->createUser('Admin', 'emp.admin.quals@example.com');
        $subject = $this->createUser('Jane Quals', 'emp.jane.quals@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $response = $this->getJson("/api/employees/{$subject->id}/qualifications");

        $response->assertOk()->assertJsonStructure(['achieved', 'missing']);
    }

    public function test_competences_returns_array(): void
    {
        $admin   = $this->createUser('Admin', 'emp.admin.comps@example.com');
        $subject = $this->createUser('Jane Comps', 'emp.jane.comps@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $response = $this->getJson("/api/employees/{$subject->id}/competences");

        $response->assertOk()->assertJsonIsArray();
    }

    public function test_responsibilities_returns_expected_keys(): void
    {
        $admin   = $this->createUser('Admin', 'emp.admin.resps@example.com');
        $subject = $this->createUser('Jane Resps', 'emp.jane.resps@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $response = $this->getJson("/api/employees/{$subject->id}/responsibilities");

        $response->assertOk()
            ->assertJsonStructure(['processes', 'information_types', 'assets', 'customers', 'suppliers', 'controls']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createUser(string $name, string $email, ?string $title = null): User
    {
        $id = DB::table('users')->insertGetId([
            'name'       => $name,
            'email'      => $email,
            'title'      => $title,
            'password'   => Hash::make('password'),
            'enabled'    => true,
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
            'name'       => 'Permission Group ' . Str::lower((string) Str::uuid()),
            'claims'     => json_encode($claims, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('access_group_user')->insert([
            'access_group_id' => $groupId,
            'user_id'         => $user->id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('users')->where('id', $user->id)->update(['updated_at' => now()]);
    }
}


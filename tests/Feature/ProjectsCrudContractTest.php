<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectsCrudContractTest extends TestCase
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

        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('project_types')) {
            Schema::create('project_types', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('partner_id')->nullable();
                $table->string('partner_name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('scopedescription')->nullable();
                $table->text('purposedescription')->nullable();
                $table->unsignedBigInteger('responsible_user_id');
                $table->unsignedBigInteger('department_id');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->unsignedBigInteger('project_type_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('project_user')) {
            Schema::create('project_user', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('project_user')->truncate();
        DB::table('projects')->truncate();
        DB::table('project_types')->truncate();
        DB::table('departments')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_projects_crud_supports_participants_and_legacy_index_filters(): void
    {
        Gate::before(static fn (): bool => true);

        $owner = $this->createUser('Project Owner', 'project.owner@example.com');
        $otherResponsible = $this->createUser('Project Other', 'project.other@example.com');
        $participantA = $this->createUser('Participant A', 'project.participant.a@example.com');
        $participantB = $this->createUser('Participant B', 'project.participant.b@example.com');

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Operations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $projectTypeId = DB::table('project_types')->insertGetId([
            'name' => 'Operational',
            'description' => 'Operational projects',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner, 'sanctum');

        $createdResponse = $this->postJson('/api/crud/projects', [
            'name' => 'Q4 Risk Reduction',
            'scopedescription' => 'Initial scope',
            'purposedescription' => 'Reduce project risks',
            'responsible_user_id' => $owner->id,
            'department_id' => $departmentId,
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
            'project_type_id' => $projectTypeId,
            'users' => [$participantA->id, $participantB->id],
        ]);

        $createdResponse->assertCreated();
        $projectId = (int) $createdResponse->json('id');

        $pivotUserIds = DB::table('project_user')
            ->where('project_id', $projectId)
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$participantA->id, $participantB->id], $pivotUserIds);

        $this->patchJson('/api/crud/projects/'.$projectId, [
            'users' => [$participantB->id],
        ])->assertOk();

        $updated = $this->getJson('/api/crud/projects/'.$projectId.'?%24select=id,users');
        $updated->assertOk();
        $this->assertSame([$participantB->id], collect($updated->json('users'))->map(fn (mixed $id): int => (int) $id)->values()->all());

        $otherProjectId = DB::table('projects')->insertGetId([
            'name' => 'Other owners project',
            'responsible_user_id' => $otherResponsible->id,
            'department_id' => $departmentId,
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'project_type_id' => $projectTypeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('projects')->insert([
            'name' => 'Archived project',
            'responsible_user_id' => $owner->id,
            'department_id' => $departmentId,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'project_type_id' => $projectTypeId,
            'archived_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $defaultIndex = $this->getJson('/api/crud/projects?paginate=0&%24select=id,name,archived_at');
        $defaultIndex->assertOk();
        $defaultRows = collect($defaultIndex->json());
        $this->assertTrue($defaultRows->contains(fn (array $row): bool => (int) $row['id'] === $projectId));
        $this->assertFalse($defaultRows->contains(fn (array $row): bool => ($row['name'] ?? null) === 'Archived project'));

        $myOnly = $this->getJson('/api/crud/projects?paginate=0&show_my_only=1&%24select=id,responsible_user_id,name');
        $myOnly->assertOk();
        $myRows = collect($myOnly->json());
        $this->assertTrue($myRows->contains(fn (array $row): bool => (int) $row['id'] === $projectId));
        $this->assertFalse($myRows->contains(fn (array $row): bool => (int) $row['id'] === $otherProjectId));

        $showArchived = $this->getJson('/api/crud/projects?paginate=0&show_archived=1&%24select=id,name,archived_at');
        $showArchived->assertOk();
        $this->assertTrue(collect($showArchived->json())->contains(fn (array $row): bool => ($row['name'] ?? null) === 'Archived project'));
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


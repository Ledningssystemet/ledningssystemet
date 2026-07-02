<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ObjectivesCrudContractTest extends TestCase
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

        if (! Schema::hasTable('objectives')) {
            Schema::create('objectives', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->date('due')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->text('action_plan')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 25);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('object_tags')) {
            Schema::create('object_tags', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tag_id');
                $table->unsignedBigInteger('object_tags_id');
                $table->string('object_tags_type');
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('object_tags')->truncate();
        DB::table('tags')->truncate();
        DB::table('objectives')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_objectives_crud_supports_tags_and_legacy_index_filters(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Objective Owner', 'objective.owner@example.com');
        $otherUser = $this->createUser('Objective Other', 'objective.other@example.com');
        $this->actingAs($user, 'sanctum');

        $activeObjectiveId = DB::table('objectives')->insertGetId([
            'name' => 'Objective With Owner',
            'description' => 'Main objective',
            'responsible_user_id' => $user->id,
            'due' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherObjectiveId = DB::table('objectives')->insertGetId([
            'name' => 'Objective With Other Owner',
            'description' => 'Owned by another user',
            'responsible_user_id' => $otherUser->id,
            'due' => '2026-11-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('objectives')->insert([
            'name' => 'Archived objective',
            'archived_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson('/api/crud/objectives/'.$activeObjectiveId, [
            'tags' => ['ISO 9001'],
        ])->assertOk();

        $tagId = (int) DB::table('tags')->where('name', 'ISO 9001')->value('id');
        $this->assertGreaterThan(0, $tagId);

        $defaultIndex = $this->getJson('/api/crud/objectives?paginate=0&%24select=id,name,archived_at,responsible_user_id,tags');
        $defaultIndex->assertOk();
        $rows = collect($defaultIndex->json());

        $this->assertTrue($rows->contains(fn (array $row): bool => (int) $row['id'] === $activeObjectiveId));
        $this->assertFalse($rows->contains(fn (array $row): bool => ($row['name'] ?? null) === 'Archived objective'));

        $activeObjective = $rows->firstWhere('id', $activeObjectiveId);
        $this->assertNotNull($activeObjective);
        $this->assertSame(['ISO 9001'], $activeObjective['tags']);

        $myOnly = $this->getJson('/api/crud/objectives?paginate=0&show_my_only=1&%24select=id,responsible_user_id,name');
        $myOnly->assertOk();
        $myRows = collect($myOnly->json());
        $this->assertTrue($myRows->contains(fn (array $row): bool => (int) $row['id'] === $activeObjectiveId));
        $this->assertFalse($myRows->contains(fn (array $row): bool => (int) $row['id'] === $otherObjectiveId));

        $responsibleFiltered = $this->getJson('/api/crud/objectives?paginate=0&responsible_user_id='.$otherUser->id.'&show_archived=1&%24select=id,responsible_user_id,name');
        $responsibleFiltered->assertOk();
        $responsibleRows = collect($responsibleFiltered->json());
        $this->assertSame([$otherObjectiveId], $responsibleRows->pluck('id')->all());

        $tagFiltered = $this->getJson('/api/crud/objectives?paginate=0&tag_id='.$tagId.'&show_archived=1&%24select=id,name');
        $tagFiltered->assertOk();
        $this->assertSame([$activeObjectiveId], collect($tagFiltered->json())->pluck('id')->all());
    }

    public function test_archive_endpoint_allows_responsible_user(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Responsible User', 'objective.responsible@example.com');
        $this->actingAs($user, 'sanctum');

        $objectiveId = DB::table('objectives')->insertGetId([
            'name' => 'Archive me',
            'responsible_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/objectives/'.$objectiveId.'/archive')
            ->assertOk()
            ->assertJsonPath('id', $objectiveId);

        $this->assertNotNull(DB::table('objectives')->where('id', $objectiveId)->value('archived_at'));
    }

    public function test_archive_endpoint_denies_non_responsible_user(): void
    {
        Gate::before(static fn (): bool => true);

        $responsible = $this->createUser('Responsible', 'objective.owner.2@example.com');
        $otherUser = $this->createUser('Other User', 'objective.other@example.com');

        $objectiveId = DB::table('objectives')->insertGetId([
            'name' => 'Archive restricted',
            'responsible_user_id' => $responsible->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($otherUser, 'sanctum');

        $this->postJson('/api/objectives/'.$objectiveId.'/archive')->assertForbidden();
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


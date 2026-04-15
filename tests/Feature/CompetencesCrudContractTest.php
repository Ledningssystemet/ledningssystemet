<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompetencesCrudContractTest extends TestCase
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

        if (! Schema::hasTable('competences')) {
            Schema::create('competences', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('competence_levels')) {
            Schema::create('competence_levels', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('competence_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('ordinal')->default(0);
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('competence_levels')->truncate();
        DB::table('competences')->truncate();
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_competences_index_is_forbidden_without_permissions(): void
    {
        $this->actingAs($this->createUser('Unauthorized', 'competence.unauthorized@example.com'), 'sanctum');

        $response = $this->getJson('/api/crud/competences');

        $response->assertForbidden();
    }

    public function test_competences_resources_are_discoverable_in_catalog(): void
    {
        $admin = $this->createUser('Catalog Admin', 'competence.catalog.admin@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $response = $this->getJson('/api/crud/resources');

        $response->assertOk();
        $resources = array_column($response->json('data'), 'resource');

        $this->assertContains('competences', $resources);
        $this->assertContains('competence-levels', $resources);
    }

    public function test_competences_crud_supports_create_update_index_and_delete(): void
    {
        $admin = $this->createUser('Competence Admin', 'competence.admin@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $createResponse = $this->postJson('/api/crud/competences', [
            'name' => 'Risk Analysis',
            'description' => 'Ability to evaluate and prioritize organizational risks.',
        ]);

        $createResponse->assertCreated();
        $competenceId = (int) $createResponse->json('id');

        $this->assertDatabaseHas('competences', [
            'id' => $competenceId,
            'name' => 'Risk Analysis',
        ]);

        $this->patchJson('/api/crud/competences/'.$competenceId, [
            'description' => 'Updated competence description.',
        ])->assertOk()->assertJsonPath('description', 'Updated competence description.');

        $this->getJson('/api/crud/competences?paginate=0&search=Risk&%24select=id,name,description')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $competenceId)
            ->assertJsonPath('0.name', 'Risk Analysis');

        $this->deleteJson('/api/crud/competences/'.$competenceId)->assertNoContent();

        $this->assertDatabaseMissing('competences', [
            'id' => $competenceId,
        ]);
    }

    public function test_competence_levels_support_parent_scoping_and_auto_ordinal(): void
    {
        $admin = $this->createUser('Level Admin', 'competence.level.admin@example.com');
        $this->grantClaims($admin, ['managementtools.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $competenceAId = DB::table('competences')->insertGetId([
            'name' => 'Competence A',
            'description' => 'A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $competenceBId = DB::table('competences')->insertGetId([
            'name' => 'Competence B',
            'description' => 'B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('competence_levels')->insert([
            'competence_id' => $competenceAId,
            'name' => 'Beginner',
            'description' => 'Base level',
            'ordinal' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->postJson('/api/crud/competence-levels', [
            'competence_id' => $competenceAId,
            'name' => 'Intermediate',
            'description' => 'Mid level',
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('ordinal', 1);
        $levelId = (int) $createResponse->json('id');

        DB::table('competence_levels')->insert([
            'competence_id' => $competenceBId,
            'name' => 'Other Parent Level',
            'description' => 'Other competence level',
            'ordinal' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $filteredResponse = $this->getJson('/api/crud/competence-levels?paginate=0&filter[competence_id]='.$competenceAId.'&sort=ordinal&%24select=id,name,ordinal,competence_id');

        $filteredResponse->assertOk()->assertJsonCount(2);
        $filteredResponse->assertJsonPath('0.name', 'Beginner');
        $filteredResponse->assertJsonPath('1.name', 'Intermediate');
        $filteredResponse->assertJsonPath('0.competence_id', $competenceAId);
        $filteredResponse->assertJsonPath('1.competence_id', $competenceAId);

        $this->patchJson('/api/crud/competence-levels/'.$levelId, [
            'ordinal' => 3,
        ])->assertOk()->assertJsonPath('ordinal', 3);

        $this->deleteJson('/api/crud/competence-levels/'.$levelId)->assertNoContent();
        $this->assertDatabaseMissing('competence_levels', ['id' => $levelId]);
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


<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentSettingsRiskMappingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureCoreTables();
        $this->truncateAssessmentTables();
    }

    public function test_it_rejects_saving_when_any_probability_consequence_pair_is_missing(): void
    {
        $admin = $this->createManagementAdmin('risk.mapping.missing@example.com');
        $this->actingAs($admin->fresh(), 'sanctum');

        $p1 = $this->insertProbabilityLevel('Low probability', 1);
        $p2 = $this->insertProbabilityLevel('High probability', 2);
        $c1 = $this->insertConsequenceLevel('Low consequence', 1);
        $c2 = $this->insertConsequenceLevel('High consequence', 2);
        $r1 = $this->insertRiskLevel('Low risk', 1);

        $response = $this->postJson('/api/assessment-settings/risk-mappings', [
            'mappings' => [
                ['probability_level_id' => $p1, 'consequence_level_id' => $c1, 'risk_level_id' => $r1],
                ['probability_level_id' => $p1, 'consequence_level_id' => $c2, 'risk_level_id' => $r1],
                ['probability_level_id' => $p2, 'consequence_level_id' => $c1, 'risk_level_id' => $r1],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mappings', 'missing_pairs']);
        $this->assertDatabaseCount('risk_level_mappings', 0);
    }

    public function test_it_saves_complete_mapping_matrix_and_returns_it_from_index(): void
    {
        $admin = $this->createManagementAdmin('risk.mapping.complete@example.com');
        $this->actingAs($admin->fresh(), 'sanctum');

        $p1 = $this->insertProbabilityLevel('P1', 1);
        $p2 = $this->insertProbabilityLevel('P2', 2);
        $c1 = $this->insertConsequenceLevel('C1', 1);
        $c2 = $this->insertConsequenceLevel('C2', 2);
        $r1 = $this->insertRiskLevel('R1', 1);
        $r2 = $this->insertRiskLevel('R2', 2);

        $saveResponse = $this->postJson('/api/assessment-settings/risk-mappings', [
            'mappings' => [
                ['probability_level_id' => $p1, 'consequence_level_id' => $c1, 'risk_level_id' => $r1],
                ['probability_level_id' => $p1, 'consequence_level_id' => $c2, 'risk_level_id' => $r2],
                ['probability_level_id' => $p2, 'consequence_level_id' => $c1, 'risk_level_id' => $r2],
                ['probability_level_id' => $p2, 'consequence_level_id' => $c2, 'risk_level_id' => $r2],
            ],
        ]);

        $saveResponse->assertOk();
        $saveResponse->assertJsonPath('message', 'Risk mappings saved.');
        $this->assertDatabaseCount('risk_level_mappings', 4);

        $indexResponse = $this->getJson('/api/assessment-settings/risk-mappings');
        $indexResponse->assertOk();
        $indexResponse->assertJsonCount(4, 'mappings');
    }

    public function test_probability_level_create_auto_assigns_ordinal_to_lowest_priority_when_missing(): void
    {
        $admin = $this->createManagementAdmin('risk.mapping.auto.ordinal@example.com');
        $this->actingAs($admin->fresh(), 'sanctum');

        $highestPriorityId = $this->insertProbabilityLevel('Highest priority', 2);
        $middlePriorityId = $this->insertProbabilityLevel('Middle priority', 1);

        $createResponse = $this->postJson('/api/crud/probability-levels', [
            'name' => 'Newest should be lowest priority',
            'description' => 'Auto ordinal test',
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('ordinal', 0);
        $newId = (int) $createResponse->json('id');

        $listResponse = $this->getJson('/api/crud/probability-levels?paginate=0&sort=-ordinal&%24select=id,name,ordinal');
        $listResponse->assertOk();
        $listResponse->assertJsonPath('0.id', $highestPriorityId);
        $listResponse->assertJsonPath('1.id', $middlePriorityId);
        $listResponse->assertJsonPath('2.id', $newId);
    }

    private function ensureCoreTables(): void
    {
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

        if (! Schema::hasTable('probability_levels')) {
            Schema::create('probability_levels', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->integer('ordinal');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('consequence_levels')) {
            Schema::create('consequence_levels', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->integer('ordinal');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('risk_levels')) {
            Schema::create('risk_levels', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->integer('ordinal');
                $table->string('color', 6);
                $table->integer('reassessment_days_withoutplans');
                $table->integer('reassessment_days_withplans');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('risk_level_mappings')) {
            Schema::create('risk_level_mappings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('probability_level_id');
                $table->unsignedBigInteger('consequence_level_id');
                $table->unsignedBigInteger('risk_level_id');
                $table->timestamps();
            });
        }
    }

    private function truncateAssessmentTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('risk_level_mappings')->truncate();
        DB::table('risk_levels')->truncate();
        DB::table('consequence_levels')->truncate();
        DB::table('probability_levels')->truncate();

        if (Schema::hasTable('access_group_user')) {
            DB::table('access_group_user')->truncate();
        }

        if (Schema::hasTable('access_groups')) {
            DB::table('access_groups')->truncate();
        }

        DB::table('users')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function createManagementAdmin(string $email): User
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Risk Mapping Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('access_groups')->insertGetId([
            'name' => 'Management Tools '.Str::lower((string) Str::uuid()),
            'claims' => json_encode(['managementtools.edit'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('access_group_user')->insert([
            'access_group_id' => $groupId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $userId)->update(['updated_at' => now()]);

        return User::query()->findOrFail($userId);
    }

    private function insertProbabilityLevel(string $name, int $ordinal): int
    {
        return (int) DB::table('probability_levels')->insertGetId([
            'name' => $name,
            'description' => $name,
            'ordinal' => $ordinal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertConsequenceLevel(string $name, int $ordinal): int
    {
        return (int) DB::table('consequence_levels')->insertGetId([
            'name' => $name,
            'description' => $name,
            'ordinal' => $ordinal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertRiskLevel(string $name, int $ordinal): int
    {
        return (int) DB::table('risk_levels')->insertGetId([
            'name' => $name,
            'description' => $name,
            'ordinal' => $ordinal,
            'color' => 'ff0000',
            'reassessment_days_withoutplans' => 365,
            'reassessment_days_withplans' => 180,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}


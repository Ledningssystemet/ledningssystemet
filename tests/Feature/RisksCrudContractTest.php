<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RisksCrudContractTest extends TestCase
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

        if (! Schema::hasTable('department_user')) {
            Schema::create('department_user', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('department_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('probability_levels')) {
            Schema::create('probability_levels', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('ordinal')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('consequence_levels')) {
            Schema::create('consequence_levels', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('ordinal')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('risk_levels')) {
            Schema::create('risk_levels', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->unsignedInteger('ordinal')->default(0);
                $table->string('color', 6)->default('ff0000');
                $table->unsignedInteger('reassessment_days_withoutplans')->default(365);
                $table->unsignedInteger('reassessment_days_withplans')->default(180);
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

        if (! Schema::hasTable('controls')) {
            Schema::create('controls', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('control_risks')) {
            Schema::create('control_risks', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('risk_id');
                $table->unsignedBigInteger('control_id');
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

        if (! Schema::hasTable('risks')) {
            Schema::create('risks', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('context_type')->nullable();
                $table->unsignedBigInteger('department_id');
                $table->unsignedBigInteger('context_id')->nullable();
                $table->text('scenariodescription')->nullable();
                $table->text('consequencedescription')->nullable();
                $table->unsignedBigInteger('riskowner_id')->nullable();
                $table->unsignedBigInteger('replacing_id')->nullable();
                $table->unsignedBigInteger('replacedby_id')->nullable();
                $table->dateTime('assessed_at')->nullable();
                $table->dateTime('replaced_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('probability_id')->nullable();
                $table->unsignedBigInteger('consequence_id')->nullable();
                $table->text('assessmentcomment')->nullable();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->unsignedBigInteger('post_probability_id')->nullable();
                $table->unsignedBigInteger('post_consequence_id')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('control_risks')->truncate();
        DB::table('object_tags')->truncate();
        DB::table('tags')->truncate();
        DB::table('risk_level_mappings')->truncate();
        DB::table('risk_levels')->truncate();
        DB::table('risks')->truncate();
        DB::table('controls')->truncate();
        DB::table('probability_levels')->truncate();
        DB::table('consequence_levels')->truncate();
        DB::table('department_user')->truncate();
        DB::table('departments')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_risks_crud_supports_legacy_filters_and_mutated_fields(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Risk Owner', 'risk.owner@example.com');
        $otherUser = $this->createUser('Risk Other', 'risk.other@example.com');
        $this->actingAs($user, 'sanctum');

        $depA = DB::table('departments')->insertGetId([
            'name' => 'Dept A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $depB = DB::table('departments')->insertGetId([
            'name' => 'Dept B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('department_user')->insert([
            'department_id' => $depA,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $probabilityId = DB::table('probability_levels')->insertGetId([
            'name' => 'High',
            'description' => 'High probability',
            'ordinal' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $consequenceId = DB::table('consequence_levels')->insertGetId([
            'name' => 'Severe',
            'description' => 'Severe consequence',
            'ordinal' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $riskLevelId = DB::table('risk_levels')->insertGetId([
            'name' => 'Critical',
            'description' => 'Critical level',
            'ordinal' => 5,
            'color' => 'ff0000',
            'reassessment_days_withoutplans' => 365,
            'reassessment_days_withplans' => 180,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('risk_level_mappings')->insert([
            'probability_level_id' => $probabilityId,
            'consequence_level_id' => $consequenceId,
            'risk_level_id' => $riskLevelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controlId = DB::table('controls')->insertGetId([
            'name' => 'Access control',
            'description' => 'Access control description',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->postJson('/api/crud/risks', [
            'name' => 'Draft risk',
            'department_id' => $depA,
            'riskowner_id' => $user->id,
            'scenariodescription' => 'Scenario text',
            'consequencedescription' => 'Consequence text',
            'probability_id' => $probabilityId,
            'consequence_id' => $consequenceId,
            'assessmentcomment' => 'Initial assessment',
            'tags' => ['ISO 27001'],
            'risk_controls' => [$controlId],
        ]);

        $createResponse->assertCreated();
        $draftRiskId = (int) $createResponse->json('id');

        $approvedRiskId = DB::table('risks')->insertGetId([
            'name' => 'Approved risk',
            'department_id' => $depB,
            'riskowner_id' => $otherUser->id,
            'scenariodescription' => 'Approved scenario',
            'assessed_at' => now(),
            'probability_id' => $probabilityId,
            'consequence_id' => $consequenceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $defaultIndex = $this->getJson('/api/crud/risks?paginate=0&%24select=id,name,assessed_at,tags,risk_controls,risk_level_id');
        $defaultIndex->assertOk();
        $defaultRows = collect($defaultIndex->json());

        $this->assertTrue($defaultRows->contains(fn (array $row): bool => (int) $row['id'] === $draftRiskId));
        $this->assertFalse($defaultRows->contains(fn (array $row): bool => (int) $row['id'] === $approvedRiskId));

        $draftRiskRow = $defaultRows->firstWhere('id', $draftRiskId);
        $this->assertSame(['ISO 27001'], $draftRiskRow['tags']);
        $this->assertSame([$controlId], $draftRiskRow['risk_controls']);
        $this->assertSame($riskLevelId, $draftRiskRow['risk_level_id']);

        $approvedOnly = $this->getJson('/api/crud/risks?paginate=0&showapproved=1&showdraft=0&%24select=id,name,assessed_at');
        $approvedOnly->assertOk();
        $approvedRows = collect($approvedOnly->json());
        $this->assertTrue($approvedRows->contains(fn (array $row): bool => (int) $row['id'] === $approvedRiskId));
        $this->assertFalse($approvedRows->contains(fn (array $row): bool => (int) $row['id'] === $draftRiskId));

        $myOnly = $this->getJson('/api/crud/risks?paginate=0&showmyonly=1&showdraft=1&showapproved=1&%24select=id,riskowner_id');
        $myOnly->assertOk();
        $myRows = collect($myOnly->json());
        $this->assertTrue($myRows->contains(fn (array $row): bool => (int) $row['id'] === $draftRiskId));
        $this->assertFalse($myRows->contains(fn (array $row): bool => (int) $row['id'] === $approvedRiskId));

        $mineByDepartment = $this->getJson('/api/crud/risks?paginate=0&department_id=0&showdraft=1&showapproved=1&%24select=id,department_id');
        $mineByDepartment->assertOk();
        $mineDepartmentRows = collect($mineByDepartment->json());
        $this->assertTrue($mineDepartmentRows->contains(fn (array $row): bool => (int) $row['id'] === $draftRiskId));
        $this->assertFalse($mineDepartmentRows->contains(fn (array $row): bool => (int) $row['id'] === $approvedRiskId));

        $byRiskLevel = $this->getJson('/api/crud/risks?paginate=0&risk_level_id='.$riskLevelId.'&showdraft=1&showapproved=1&%24select=id,name');
        $byRiskLevel->assertOk();
        $this->assertTrue(collect($byRiskLevel->json())->contains(fn (array $row): bool => (int) $row['id'] === $draftRiskId));
    }

    public function test_risks_index_handles_missing_legacy_context_model_without_500(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Risk Legacy Context', 'risk.legacy.context@example.com');
        $this->actingAs($user, 'sanctum');

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Dept Legacy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $riskId = DB::table('risks')->insertGetId([
            'name' => 'Legacy risk {name}',
            'department_id' => $departmentId,
            'riskowner_id' => $user->id,
            'context_type' => 'App\\Models\\Company',
            'context_id' => 1,
            'scenariodescription' => 'Legacy scenario',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/crud/risks?paginate=0&showdraft=1&showapproved=1&%24select=id,name,context_type,context_id');

        $response->assertOk();
        $rows = collect($response->json());
        $risk = $rows->firstWhere('id', $riskId);

        $this->assertNotNull($risk);
        $this->assertSame('Legacy risk {name}', $risk['name']);
    }

    public function test_risks_translated_attributes_work_without_context_in_select(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Risk Translation Test', 'risk.translation@example.com');
        $this->actingAs($user, 'sanctum');

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Security Dept',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a risk with context and placeholders
        $riskId = DB::table('risks')->insertGetId([
            'name' => 'Risk in {name}',
            'department_id' => $departmentId,
            'riskowner_id' => $user->id,
            'context_type' => 'App\\Models\\Department',
            'context_id' => $departmentId,
            'scenariodescription' => 'Scenario affecting {NAME}',
            'consequencedescription' => 'Impact on {name} operations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Fetch without context_type and context_id in $select
        // This simulates the real-world scenario where users don't select these fields
        $response = $this->getJson('/api/crud/risks?paginate=0&showdraft=1&showapproved=1&%24select=id,name,scenariodescription,consequencedescription,translated_name,translated_scenariodescription,translated_consequencedescription');

        $response->assertOk();
        $rows = collect($response->json());
        $risk = $rows->firstWhere('id', $riskId);

        $this->assertNotNull($risk);

        // name accessor applies replacement automatically
        $this->assertSame('Risk in Security Dept', $risk['name']);

        // raw fields keep their placeholders when accessed directly via CRUD
        $this->assertSame('Scenario affecting {NAME}', $risk['scenariodescription']);
        $this->assertSame('Impact on {name} operations', $risk['consequencedescription']);

        // Translated attributes also perform lazy-load and replace placeholders (same as name accessor)
        $this->assertSame('Risk in Security Dept', $risk['translated_name']);
        $this->assertSame('Scenario affecting Security Dept', $risk['translated_scenariodescription']);
        $this->assertSame('Impact on Security Dept operations', $risk['translated_consequencedescription']);

        // IMPORTANT: context_type and context_id should NOT be returned since they weren't in $select
        $this->assertArrayNotHasKey('context_type', $risk);
        $this->assertArrayNotHasKey('context_id', $risk);
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


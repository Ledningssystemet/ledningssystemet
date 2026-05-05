<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ControlRequirementsCrudContractTest extends TestCase
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

        if (! Schema::hasTable('controls')) {
            Schema::create('controls', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->text('statusdescription')->nullable();
                $table->date('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('requirement_sources')) {
            Schema::create('requirement_sources', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('reference', 20);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedInteger('max_sanction_fee')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('requirements')) {
            Schema::create('requirements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('requirement_source_id');
                $table->boolean('applicable')->nullable();
                $table->string('name', 100);
                $table->string('reference', 20);
                $table->unsignedInteger('ordinal')->default(0);
                $table->text('description')->nullable();
                $table->text('governance')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('control_requirements')) {
            Schema::create('control_requirements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('control_id');
                $table->unsignedBigInteger('requirement_id');
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('control_requirements')->truncate();
        DB::table('requirements')->truncate();
        DB::table('requirement_sources')->truncate();
        DB::table('controls')->truncate();
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_control_requirements_support_control_scoping_and_crud(): void
    {
        $editor = $this->createUser('Requirement Editor', 'requirement.editor@example.com');
        $this->grantClaims($editor, ['requirements.edit', 'controls.read']);
        $this->actingAs($editor->fresh(), 'sanctum');

        $controlAId = DB::table('controls')->insertGetId([
            'name' => 'Control A',
            'description' => 'Control A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controlBId = DB::table('controls')->insertGetId([
            'name' => 'Control B',
            'description' => 'Control B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sourceId = DB::table('requirement_sources')->insertGetId([
            'reference' => 'SRC-C',
            'name' => 'Source C',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requirementAId = DB::table('requirements')->insertGetId([
            'requirement_source_id' => $sourceId,
            'applicable' => true,
            'name' => 'Requirement A',
            'reference' => 'REQ-A',
            'ordinal' => 0,
            'description' => null,
            'governance' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requirementBId = DB::table('requirements')->insertGetId([
            'requirement_source_id' => $sourceId,
            'applicable' => true,
            'name' => 'Requirement B',
            'reference' => 'REQ-B',
            'ordinal' => 1,
            'description' => null,
            'governance' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('control_requirements')->insert([
            'control_id' => $controlBId,
            'requirement_id' => $requirementAId,
        ]);

        $createResponse = $this->postJson('/api/crud/control-requirements', [
            'control_id' => $controlAId,
            'requirement_id' => $requirementAId,
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('control_id', $controlAId);
        $createResponse->assertJsonPath('requirement_id', $requirementAId);
        $linkId = (int) $createResponse->json('id');

        $duplicateResponse = $this->postJson('/api/crud/control-requirements', [
            'control_id' => $controlAId,
            'requirement_id' => $requirementAId,
        ]);
        $duplicateResponse->assertUnprocessable();

        $filteredResponse = $this->getJson('/api/crud/control-requirements?paginate=0&filter[control_id]='.$controlAId.'&%24select=id,control_id,requirement_id');
        $filteredResponse->assertOk()->assertJsonCount(1);
        $filteredResponse->assertJsonPath('0.control_id', $controlAId);
        $filteredResponse->assertJsonPath('0.requirement_id', $requirementAId);

        $this->patchJson('/api/crud/control-requirements/'.$linkId, [
            'requirement_id' => $requirementBId,
        ])->assertOk()->assertJsonPath('requirement_id', $requirementBId);

        $this->deleteJson('/api/crud/control-requirements/'.$linkId)->assertNoContent();
        $this->assertDatabaseMissing('control_requirements', ['id' => $linkId]);
    }

    public function test_control_requirements_require_requirements_edit_for_mutations(): void
    {
        $reader = $this->createUser('Requirement Reader', 'requirement.reader@example.com');
        $this->grantClaims($reader, ['requirements.read']);
        $this->actingAs($reader->fresh(), 'sanctum');

        $controlId = DB::table('controls')->insertGetId([
            'name' => 'Read only control',
            'description' => 'Read only control',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sourceId = DB::table('requirement_sources')->insertGetId([
            'reference' => 'SRC-R',
            'name' => 'Source R',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requirementId = DB::table('requirements')->insertGetId([
            'requirement_source_id' => $sourceId,
            'applicable' => true,
            'name' => 'Read only requirement',
            'reference' => 'REQ-R',
            'ordinal' => 0,
            'description' => null,
            'governance' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/crud/control-requirements?paginate=0&%24select=id,control_id,requirement_id')->assertOk();

        $this->postJson('/api/crud/control-requirements', [
            'control_id' => $controlId,
            'requirement_id' => $requirementId,
        ])->assertForbidden();
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


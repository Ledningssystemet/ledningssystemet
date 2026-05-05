<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequirementsCrudContractTest extends TestCase
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
                $table->boolean('iscontrol')->default(false);
                $table->boolean('applicable')->nullable();
                $table->string('name', 100);
                $table->string('reference', 20);
                $table->unsignedInteger('ordinal')->default(0);
                $table->text('description')->nullable();
                $table->text('governance')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('requirements')->truncate();
        DB::table('requirement_sources')->truncate();
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_requirements_support_parent_scoping_and_auto_ordinal(): void
    {
        $admin = $this->createUser('Requirements Admin', 'requirements.admin@example.com');
        $this->grantClaims($admin, ['requirements.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $sourceAId = DB::table('requirement_sources')->insertGetId([
            'reference' => 'SRC-A',
            'name' => 'Source A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sourceBId = DB::table('requirement_sources')->insertGetId([
            'reference' => 'SRC-B',
            'name' => 'Source B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('requirements')->insert([
            'requirement_source_id' => $sourceAId,
            'iscontrol' => false,
            'applicable' => true,
            'name' => 'Existing A',
            'reference' => 'REQ-A-0',
            'ordinal' => 0,
            'description' => 'Existing requirement in source A',
            'governance' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('requirements')->insert([
            'requirement_source_id' => $sourceBId,
            'iscontrol' => false,
            'applicable' => true,
            'name' => 'Existing B',
            'reference' => 'REQ-B-0',
            'ordinal' => 0,
            'description' => 'Existing requirement in source B',
            'governance' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->postJson('/api/crud/requirements', [
            'requirement_source_id' => $sourceAId,
            'iscontrol' => false,
            'applicable' => true,
            'name' => 'Created A',
            'reference' => 'REQ-A-1',
            'description' => 'Created requirement in source A',
            'governance' => 'Governance text',
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('ordinal', 1);
        $requirementId = (int) $createResponse->json('id');

        $filteredResponse = $this->getJson('/api/crud/requirements?paginate=0&filter[requirement_source_id]='.$sourceAId.'&sort=ordinal&%24select=id,reference,ordinal,requirement_source_id');

        $filteredResponse->assertOk()->assertJsonCount(2);
        $filteredResponse->assertJsonPath('0.reference', 'REQ-A-0');
        $filteredResponse->assertJsonPath('0.ordinal', 0);
        $filteredResponse->assertJsonPath('1.reference', 'REQ-A-1');
        $filteredResponse->assertJsonPath('1.ordinal', 1);
        $filteredResponse->assertJsonPath('0.requirement_source_id', $sourceAId);
        $filteredResponse->assertJsonPath('1.requirement_source_id', $sourceAId);

        $this->patchJson('/api/crud/requirements/'.$requirementId, [
            'ordinal' => 3,
        ])->assertOk()->assertJsonPath('ordinal', 3);

        $otherSourceResponse = $this->getJson('/api/crud/requirements?paginate=0&filter[requirement_source_id]='.$sourceBId.'&sort=ordinal&%24select=id,reference,ordinal,requirement_source_id');

        $otherSourceResponse->assertOk()->assertJsonCount(1);
        $otherSourceResponse->assertJsonPath('0.reference', 'REQ-B-0');
        $otherSourceResponse->assertJsonPath('0.ordinal', 0);
        $otherSourceResponse->assertJsonPath('0.requirement_source_id', $sourceBId);

        $this->deleteJson('/api/crud/requirements/'.$requirementId)->assertNoContent();
        $this->assertDatabaseMissing('requirements', ['id' => $requirementId]);
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


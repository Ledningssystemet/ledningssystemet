<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequirementSourcesApprovalCrudTest extends TestCase
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
                $table->boolean('applicable')->nullable();
                $table->string('name', 100);
                $table->string('reference', 20);
                $table->unsignedInteger('ordinal')->default(0);
                $table->text('description')->nullable();
                $table->text('governance')->nullable();
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

        if (! Schema::hasTable('control_requirements')) {
            Schema::create('control_requirements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('control_id');
                $table->unsignedBigInteger('requirement_id');
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('control_requirements')->truncate();
        DB::table('controls')->truncate();
        DB::table('requirements')->truncate();
        DB::table('requirement_sources')->truncate();
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_only_responsible_user_can_approve_requirement_source(): void
    {
        $responsible = $this->createUser('Responsible User', 'responsible@example.com');
        $other = $this->createUser('Other User', 'other@example.com');
        $this->grantClaims($responsible, ['requirements.edit']);
        $this->grantClaims($other, ['requirements.edit']);

        $sourceId = DB::table('requirement_sources')->insertGetId([
            'reference' => 'SRC-APPROVE',
            'name' => 'Approval Source',
            'responsible_user_id' => $responsible->id,
            'approved_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($other->fresh(), 'sanctum');
        $this->patchJson('/api/crud/requirement_sources/'.$sourceId, [
            'approved_at' => now()->toIso8601String(),
        ])->assertStatus(422);

        $this->assertDatabaseHas('requirement_sources', [
            'id' => $sourceId,
            'approved_at' => null,
        ]);

        $this->actingAs($responsible->fresh(), 'sanctum');
        $this->patchJson('/api/crud/requirement_sources/'.$sourceId, [
            'approved_at' => now()->toIso8601String(),
        ])->assertOk();

        $this->assertNotNull(DB::table('requirement_sources')->where('id', $sourceId)->value('approved_at'));
    }

    public function test_requirement_source_needsapproval_and_status_reflect_changes_since_approval(): void
    {
        Config::set('ledningssystemet.requirement_source_approval_max_age_days', 365);

        $admin = $this->createUser('Requirements Admin', 'requirements.approval.admin@example.com');
        $this->grantClaims($admin, ['requirements.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $approvedAt = Carbon::parse('2026-01-01 10:00:00');

        $sourceNeedingApproval = DB::table('requirement_sources')->insertGetId([
            'reference' => 'SRC-CHANGED',
            'name' => 'Changed Source',
            'responsible_user_id' => $admin->id,
            'approved_at' => $approvedAt,
            'created_at' => $approvedAt->copy()->subDay(),
            'updated_at' => $approvedAt->copy()->subMinute(),
        ]);

        DB::table('requirements')->insert([
            'requirement_source_id' => $sourceNeedingApproval,
            'applicable' => true,
            'name' => 'Changed Requirement',
            'reference' => 'REQ-CHANGED',
            'ordinal' => 1,
            'description' => null,
            'governance' => null,
            'created_at' => $approvedAt->copy()->subHour(),
            'updated_at' => $approvedAt->copy()->addHour(),
        ]);

        $sourceStillApproved = DB::table('requirement_sources')->insertGetId([
            'reference' => 'SRC-STABLE',
            'name' => 'Stable Source',
            'responsible_user_id' => $admin->id,
            'approved_at' => $approvedAt,
            'created_at' => $approvedAt->copy()->subDay(),
            'updated_at' => $approvedAt->copy()->subHour(),
        ]);

        DB::table('requirements')->insert([
            'requirement_source_id' => $sourceStillApproved,
            'applicable' => true,
            'name' => 'Stable Requirement',
            'reference' => 'REQ-STABLE',
            'ordinal' => 1,
            'description' => null,
            'governance' => null,
            'created_at' => $approvedAt->copy()->subDay(),
            'updated_at' => $approvedAt->copy()->subHour(),
        ]);

        $response = $this->getJson('/api/crud/requirement_sources?paginate=0&sort=reference&%24select=id,reference,responsible_user_id,needsapproval,status,approval_reason_types');
        $response->assertOk();

        $rows = collect($response->json())->keyBy('reference');
        $this->assertTrue((bool) ($rows->get('SRC-CHANGED')['needsapproval'] ?? false));
        $this->assertSame('warning', $rows->get('SRC-CHANGED')['status']['level'] ?? null);
        $this->assertContains('requirements_changed', $rows->get('SRC-CHANGED')['approval_reason_types'] ?? []);
        $this->assertFalse((bool) ($rows->get('SRC-STABLE')['needsapproval'] ?? true));
        $this->assertSame('success', $rows->get('SRC-STABLE')['status']['level'] ?? null);
    }

    public function test_requirement_source_needsapproval_when_previous_approval_is_too_old(): void
    {
        Config::set('ledningssystemet.requirement_source_approval_max_age_days', 30);

        $admin = $this->createUser('Requirements Admin', 'requirements.approval.age@example.com');
        $this->grantClaims($admin, ['requirements.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $sourceId = DB::table('requirement_sources')->insertGetId([
            'reference' => 'SRC-STALE',
            'name' => 'Stale Source',
            'responsible_user_id' => $admin->id,
            'approved_at' => now()->subDays(40),
            'created_at' => now()->subDays(90),
            'updated_at' => now()->subDays(40),
        ]);

        $response = $this->getJson('/api/crud/requirement_sources?paginate=0&filter[id]='.$sourceId.'&%24select=id,responsible_user_id,needsapproval,status,approval_reason_types');
        $response->assertOk()->assertJsonCount(1);

        $row = $response->json('0');
        $this->assertTrue((bool) ($row['needsapproval'] ?? false));
        $this->assertSame('warning', $row['status']['level'] ?? null);
        $this->assertContains('stale_approval', $row['approval_reason_types'] ?? []);
    }

    public function test_requirements_expose_change_type_and_warning_status_since_latest_source_approval(): void
    {
        $admin = $this->createUser('Requirements Admin', 'requirements.changed.admin@example.com');
        $this->grantClaims($admin, ['requirements.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $approvedAt = Carbon::parse('2026-01-01 10:00:00');
        $sourceId = DB::table('requirement_sources')->insertGetId([
            'reference' => 'SRC-REQ-CHANGED',
            'name' => 'Source With Requirement Changes',
            'responsible_user_id' => $admin->id,
            'approved_at' => $approvedAt,
            'created_at' => $approvedAt->copy()->subDay(),
            'updated_at' => $approvedAt->copy()->subMinute(),
        ]);

        DB::table('requirements')->insert([
            [
                'requirement_source_id' => $sourceId,
                'applicable' => true,
                'name' => 'Changed After Approval',
                'reference' => 'REQ-NEW',
                'ordinal' => 1,
                'description' => null,
                'governance' => null,
                'created_at' => $approvedAt->copy()->addMinute(),
                'updated_at' => $approvedAt->copy()->addMinute(),
            ],
            [
                'requirement_source_id' => $sourceId,
                'applicable' => true,
                'name' => 'Changed After Approval',
                'reference' => 'REQ-CHANGED',
                'ordinal' => 2,
                'description' => null,
                'governance' => null,
                'created_at' => $approvedAt->copy()->subDay(),
                'updated_at' => $approvedAt->copy()->addHour(),
            ],
            [
                'requirement_source_id' => $sourceId,
                'applicable' => true,
                'name' => 'Unchanged Before Approval',
                'reference' => 'REQ-OLD',
                'ordinal' => 3,
                'description' => null,
                'governance' => null,
                'created_at' => $approvedAt->copy()->subDay(),
                'updated_at' => $approvedAt->copy()->subHour(),
            ],
        ]);

        $response = $this->getJson('/api/crud/requirements?paginate=0&filter[requirement_source_id]='.$sourceId.'&sort=ordinal&%24select=id,reference,changed_since_source_approval,changed_since_source_approval_type,status');
        $response->assertOk();

        $rows = collect($response->json())->keyBy('reference');
        $this->assertTrue((bool) ($rows->get('REQ-NEW')['changed_since_source_approval'] ?? false));
        $this->assertSame('added', $rows->get('REQ-NEW')['changed_since_source_approval_type'] ?? null);
        $this->assertSame('warning', $rows->get('REQ-NEW')['status']['level'] ?? null);

        $this->assertTrue((bool) ($rows->get('REQ-CHANGED')['changed_since_source_approval'] ?? false));
        $this->assertSame('changed', $rows->get('REQ-CHANGED')['changed_since_source_approval_type'] ?? null);
        $this->assertSame('warning', $rows->get('REQ-CHANGED')['status']['level'] ?? null);

        $this->assertFalse((bool) ($rows->get('REQ-OLD')['changed_since_source_approval'] ?? true));
        $this->assertSame('unchanged', $rows->get('REQ-OLD')['changed_since_source_approval_type'] ?? null);
        $this->assertSame('success', $rows->get('REQ-OLD')['status']['level'] ?? null);
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

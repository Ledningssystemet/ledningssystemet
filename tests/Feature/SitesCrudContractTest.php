<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SitesCrudContractTest extends TestCase
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
                $table->unsignedBigInteger('site_id')->nullable();
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

        if (! Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->unsignedBigInteger('external_provider_group_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('object_tags')) {
            Schema::create('object_tags', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tag_id');
                $table->string('object_tags_type');
                $table->unsignedBigInteger('object_tags_id');
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('object_tags')->truncate();
        DB::table('tags')->truncate();
        DB::table('assets')->truncate();
        DB::table('departments')->truncate();
        DB::table('sites')->truncate();
        DB::table('access_group_user')->truncate();
        DB::table('access_groups')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_sites_crud_supports_assignments_counts_and_tags(): void
    {
        $admin = $this->createUser('Site Admin', 'site.admin@example.com');
        $this->grantClaims($admin, ['systemadministrator.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $responsible = $this->createUser('Site Responsible', 'site.responsible@example.com');
        $member = $this->createUser('Site Member', 'site.member@example.com');

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Operations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assetId = DB::table('assets')->insertGetId([
            'name' => 'HQ Server Rack',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->postJson('/api/crud/sites', [
            'name' => 'Headquarter',
            'responsible_user_id' => $responsible->id,
            'users' => [$member->id],
            'departments' => [$departmentId],
            'assets' => [$assetId],
            'tags' => ['north'],
        ]);

        $createResponse->assertCreated();
        $siteId = (int) $createResponse->json('id');

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'site_id' => $siteId,
        ]);
        $this->assertDatabaseHas('departments', [
            'id' => $departmentId,
            'site_id' => $siteId,
        ]);
        $this->assertDatabaseHas('assets', [
            'id' => $assetId,
            'site_id' => $siteId,
        ]);

        $indexResponse = $this->getJson('/api/crud/sites?paginate=0&search=Headquarter&%24select=id,name');

        $indexResponse->assertOk();
        $rows = $indexResponse->json();

        $this->assertCount(1, $rows);
        $this->assertSame([$member->id], $rows[0]['users']);
        $this->assertSame([$departmentId], $rows[0]['departments']);
        $this->assertSame([$assetId], $rows[0]['assets']);
        $this->assertSame(['north'], $rows[0]['tags']);
        $this->assertSame(1, $rows[0]['userscount']);
        $this->assertSame(1, $rows[0]['departmentscount']);
        $this->assertSame(1, $rows[0]['assetscount']);
        $this->assertIsBool($rows[0]['classified']);
        $this->assertFalse($rows[0]['can_delete']);
    }

    public function test_sites_hide_without_issues_filter_returns_unclassified_sites_only(): void
    {
        $admin = $this->createUser('Site Filter Admin', 'site.filter.admin@example.com');
        $this->grantClaims($admin, ['systemadministrator.edit']);
        $this->actingAs($admin->fresh(), 'sanctum');

        $responsible = $this->createUser('Classified Responsible', 'site.classified.responsible@example.com');

        $this->postJson('/api/crud/sites', [
            'name' => 'Classified Site',
            'responsible_user_id' => $responsible->id,
        ])->assertCreated();

        $this->postJson('/api/crud/sites', [
            'name' => 'Unclassified Site',
        ])->assertCreated();

        $response = $this->getJson('/api/crud/sites?paginate=0&hide_without_issues=1&%24select=id,name');

        $response->assertOk();
        $rows = $response->json();

        $this->assertCount(1, $rows);
        $this->assertSame('Unclassified Site', $rows[0]['name']);
        $this->assertFalse($rows[0]['classified']);
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


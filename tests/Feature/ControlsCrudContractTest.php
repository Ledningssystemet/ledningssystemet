<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ControlsCrudContractTest extends TestCase
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

        if (! Schema::hasTable('control_actions')) {
            Schema::create('control_actions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('control_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('responsible_id')->nullable();
                $table->date('due')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->date('originaldue')->nullable();
                $table->integer('estimated_cost')->nullable();
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
        DB::table('control_actions')->truncate();
        DB::table('controls')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_controls_crud_supports_tags_and_legacy_index_filters(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Control Owner', 'control.owner@example.com');
        $otherUser = $this->createUser('Control Other', 'control.other@example.com');
        $this->actingAs($user, 'sanctum');

        $activeControlId = DB::table('controls')->insertGetId([
            'name' => 'Control With Owner',
            'description' => 'Main control',
            'responsible_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('controls')->insert([
            'name' => 'Other user control',
            'description' => 'Owned by another user',
            'responsible_user_id' => $otherUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $missingOwnerControlId = DB::table('controls')->insertGetId([
            'name' => 'Missing owner control',
            'description' => 'No responsible user',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('controls')->insert([
            'name' => 'Not applicable control',
            'description' => 'Should be hidden by default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson('/api/crud/controls/'.$activeControlId, [
            'tags' => ['ISO 27001'],
        ])->assertOk();

        $tagId = (int) DB::table('tags')->where('name', 'ISO 27001')->value('id');
        $this->assertGreaterThan(0, $tagId);

        $defaultIndex = $this->getJson('/api/crud/controls?paginate=0&%24select=id,name,responsible_user_id,tags');
        $defaultIndex->assertOk();
        $rows = collect($defaultIndex->json());

        $this->assertTrue($rows->contains(fn (array $row): bool => (int) $row['id'] === $activeControlId));
        $this->assertTrue($rows->contains(fn (array $row): bool => ($row['name'] ?? null) === 'Not applicable control'));

        $activeControl = $rows->firstWhere('id', $activeControlId);
        $this->assertNotNull($activeControl);
        $this->assertSame(['ISO 27001'], $activeControl['tags']);

        $myOnly = $this->getJson('/api/crud/controls?paginate=0&show_my_only=1&%24select=id,responsible_user_id,name');
        $myOnly->assertOk();
        $myRows = collect($myOnly->json());
        $this->assertTrue($myRows->contains(fn (array $row): bool => (int) $row['id'] === $activeControlId));
        $this->assertFalse($myRows->contains(fn (array $row): bool => ($row['name'] ?? null) === 'Other user control'));

        $hideWithoutIssues = $this->getJson('/api/crud/controls?paginate=0&hide_without_issues=1&%24select=id,name,responsible_user_id');
        $hideWithoutIssues->assertOk();
        $issueRows = collect($hideWithoutIssues->json());
        $this->assertTrue($issueRows->contains(fn (array $row): bool => (int) $row['id'] === $missingOwnerControlId));
        $this->assertFalse($issueRows->contains(fn (array $row): bool => (int) $row['id'] === $activeControlId));

        $tagFiltered = $this->getJson('/api/crud/controls?paginate=0&tag_id='.$tagId.'&%24select=id,name');
        $tagFiltered->assertOk();
        $this->assertSame([$activeControlId], collect($tagFiltered->json())->pluck('id')->all());

        $showNotApplicable = $this->getJson('/api/crud/controls?paginate=0&%24select=id,name');
        $showNotApplicable->assertOk();
        $this->assertTrue(collect($showNotApplicable->json())->contains(fn (array $row): bool => ($row['name'] ?? null) === 'Not applicable control'));
    }

    public function test_control_actions_endpoint_supports_control_scoping(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Actions User', 'control.actions@example.com');
        $this->actingAs($user, 'sanctum');

        $controlA = DB::table('controls')->insertGetId([
            'name' => 'Control A',
            'description' => 'A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controlB = DB::table('controls')->insertGetId([
            'name' => 'Control B',
            'description' => 'B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('control_actions')->insert([
            [
                'control_id' => $controlA,
                'name' => 'Action A1',
                'description' => 'Action for A',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'control_id' => $controlB,
                'name' => 'Action B1',
                'description' => 'Action for B',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/crud/control-actions?paginate=0&filter[control_id]='.$controlA.'&%24select=id,control_id,name');
        $response->assertOk();

        $rows = collect($response->json());
        $this->assertCount(1, $rows);
        $this->assertTrue($rows->every(fn (array $row): bool => (int) $row['control_id'] === $controlA));
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


<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgreementsCrudContractTest extends TestCase
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

        if (! Schema::hasTable('agreements')) {
            Schema::create('agreements', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->date('startdate')->nullable();
                $table->date('enddate')->nullable();
                $table->date('reminderdate')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->timestamp('archived_at')->nullable();
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
        DB::table('agreements')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_agreements_crud_supports_tags_and_legacy_index_filters(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Agreement Owner', 'agreement.owner@example.com');
        $this->actingAs($user, 'sanctum');

        $activeAgreementId = DB::table('agreements')->insertGetId([
            'name' => 'Agreement With Tags',
            'description' => 'Main agreement',
            'responsible_user_id' => $user->id,
            'startdate' => '2026-01-01',
            'enddate' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agreements')->insert([
            'name' => 'Archived agreement',
            'archived_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $missingFieldsId = DB::table('agreements')->insertGetId([
            'name' => 'Agreement Missing Fields',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson('/api/crud/agreements/'.$activeAgreementId, [
            'tags' => ['ISO 27001'],
        ])->assertOk();

        $tagId = (int) DB::table('tags')->where('name', 'ISO 27001')->value('id');
        $this->assertGreaterThan(0, $tagId);

        $defaultIndex = $this->getJson('/api/crud/agreements?paginate=0&%24select=id,name,archived_at,responsible_user_id,startdate,enddate,tags');
        $defaultIndex->assertOk();
        $rows = collect($defaultIndex->json());

        $this->assertTrue($rows->contains(fn (array $row): bool => (int) $row['id'] === $activeAgreementId));
        $this->assertFalse($rows->contains(fn (array $row): bool => ($row['name'] ?? null) === 'Archived agreement'));

        $activeAgreement = $rows->firstWhere('id', $activeAgreementId);
        $this->assertNotNull($activeAgreement);
        $this->assertSame(['ISO 27001'], $activeAgreement['tags']);

        $hideWithoutIssues = $this->getJson('/api/crud/agreements?paginate=0&hide_without_issues=1&%24select=id,name,responsible_user_id,startdate,enddate');
        $hideWithoutIssues->assertOk();
        $issueRows = collect($hideWithoutIssues->json());
        $this->assertTrue($issueRows->contains(fn (array $row): bool => (int) $row['id'] === $missingFieldsId));
        $this->assertFalse($issueRows->contains(fn (array $row): bool => (int) $row['id'] === $activeAgreementId));

        $tagFiltered = $this->getJson('/api/crud/agreements?paginate=0&tag_id='.$tagId.'&show_archived=1&%24select=id,name');
        $tagFiltered->assertOk();
        $this->assertSame([$activeAgreementId], collect($tagFiltered->json())->pluck('id')->all());
    }

    public function test_archive_endpoint_allows_responsible_user(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Responsible User', 'agreement.responsible@example.com');
        $this->actingAs($user, 'sanctum');

        $agreementId = DB::table('agreements')->insertGetId([
            'name' => 'Archive me',
            'responsible_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/agreements/'.$agreementId.'/archive')
            ->assertOk()
            ->assertJsonPath('id', $agreementId);

        $this->assertNotNull(DB::table('agreements')->where('id', $agreementId)->value('archived_at'));
    }

    public function test_archive_endpoint_denies_non_responsible_user(): void
    {
        Gate::before(static fn (): bool => true);

        $responsible = $this->createUser('Responsible', 'agreement.owner.2@example.com');
        $otherUser = $this->createUser('Other User', 'agreement.other@example.com');

        $agreementId = DB::table('agreements')->insertGetId([
            'name' => 'Archive restricted',
            'responsible_user_id' => $responsible->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($otherUser, 'sanctum');

        $this->postJson('/api/agreements/'.$agreementId.'/archive')->assertForbidden();
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


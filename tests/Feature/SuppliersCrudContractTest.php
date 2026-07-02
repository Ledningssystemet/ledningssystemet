<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SuppliersCrudContractTest extends TestCase
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

        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->text('processoragreementdescription')->nullable();
                $table->boolean('dataprocessor')->nullable();
                $table->string('external_supplier_id')->nullable();
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
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('process_activities')) {
            Schema::create('process_activities', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('process_activity_supplier')) {
            Schema::create('process_activity_supplier', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('process_activity_id');
                $table->unsignedBigInteger('supplier_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agreements')) {
            Schema::create('agreements', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_categories')) {
            Schema::create('supplier_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('reassessment_interval')->nullable();
                $table->string('formulaname')->nullable();
                $table->boolean('defaultvalue')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_supplier_category')) {
            Schema::create('supplier_supplier_category', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('supplier_category_id');
                $table->boolean('applicable');
                $table->string('updated_by_name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_requirements')) {
            Schema::create('supplier_requirements', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('supplier_category_id');
                $table->boolean('reassessment');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_supplier_requirement')) {
            Schema::create('supplier_supplier_requirement', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('supplier_requirement_id');
                $table->string('updated_by_name');
                $table->text('note')->nullable();
                $table->boolean('satisfactory');
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('supplier_supplier_requirement')->truncate();
        DB::table('supplier_requirements')->truncate();
        DB::table('supplier_supplier_category')->truncate();
        DB::table('supplier_categories')->truncate();
        DB::table('agreements')->truncate();
        DB::table('assets')->truncate();
        DB::table('process_activity_supplier')->truncate();
        DB::table('process_activities')->truncate();
        DB::table('object_tags')->truncate();
        DB::table('tags')->truncate();
        DB::table('suppliers')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_suppliers_crud_supports_tags_filters_and_summary_appends(): void
    {
        Gate::before(static fn (): bool => true);

        $owner = $this->createUser('Supplier Owner', 'supplier.owner@example.com');
        $otherUser = $this->createUser('Other Owner', 'supplier.other@example.com');
        $this->actingAs($owner, 'sanctum');

        $supplierId = DB::table('suppliers')->insertGetId([
            'name' => 'Vendor Alpha',
            'description' => 'Primary vendor',
            'responsible_user_id' => $owner->id,
            'external_supplier_id' => 'SUP-100',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $issueSupplierId = DB::table('suppliers')->insertGetId([
            'name' => 'Vendor Beta',
            'responsible_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $completeSupplierId = DB::table('suppliers')->insertGetId([
            'name' => 'Vendor Gamma',
            'responsible_user_id' => $otherUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = DB::table('supplier_categories')->insertGetId([
            'name' => 'IT services',
            'description' => 'IT and software vendors',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondaryCategoryId = DB::table('supplier_categories')->insertGetId([
            'name' => 'Logistics',
            'description' => 'Shipping and distribution',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('supplier_supplier_category')->insert([
            'supplier_id' => $supplierId,
            'supplier_category_id' => $categoryId,
            'applicable' => true,
            'updated_by_name' => $owner->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('supplier_supplier_category')->insert([
            'supplier_id' => $completeSupplierId,
            'supplier_category_id' => $categoryId,
            'applicable' => true,
            'updated_by_name' => $otherUser->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('supplier_supplier_category')->insert([
            'supplier_id' => $completeSupplierId,
            'supplier_category_id' => $secondaryCategoryId,
            'applicable' => false,
            'updated_by_name' => $otherUser->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processActivityPayload = [
            'name' => 'Due diligence',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('process_activities', 'bpmnId')) {
            $processActivityPayload['bpmnId'] = 'activity_supplier_test_1';
        }

        if (Schema::hasColumn('process_activities', 'process_id')) {
            if (! Schema::hasTable('departments')) {
                Schema::create('departments', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                    $table->timestamps();
                });
            }

            if (! Schema::hasTable('processes')) {
                Schema::create('processes', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                    $table->unsignedBigInteger('department_id')->nullable();
                    $table->unsignedBigInteger('responsible_user_id')->nullable();
                    $table->boolean('isstartprocess')->default(false);
                    $table->boolean('dataprocessor')->default(false);
                    $table->timestamps();
                });
            }

            $departmentPayload = [
                'name' => 'Supplier test department '.uniqid(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $departmentId = DB::table('departments')->insertGetId($departmentPayload);

            $processPayload = [
                'name' => 'Supplier test process '.uniqid(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('processes', 'department_id')) {
                $processPayload['department_id'] = $departmentId;
            }

            if (Schema::hasColumn('processes', 'responsible_user_id')) {
                $processPayload['responsible_user_id'] = $owner->id;
            }

            if (Schema::hasColumn('processes', 'isstartprocess')) {
                $processPayload['isstartprocess'] = false;
            }

            if (Schema::hasColumn('processes', 'dataprocessor')) {
                $processPayload['dataprocessor'] = false;
            }

            if (Schema::hasColumn('processes', 'description')) {
                $processPayload['description'] = null;
            }

            $processActivityPayload['process_id'] = DB::table('processes')->insertGetId($processPayload);
        }

        if (Schema::hasColumn('process_activities', 'description')) {
            $processActivityPayload['description'] = null;
        }

        $activityId = DB::table('process_activities')->insertGetId($processActivityPayload);

        $processActivitySupplierPayload = [
            'process_activity_id' => $activityId,
            'supplier_id' => $supplierId,
        ];

        if (Schema::hasColumn('process_activity_supplier', 'created_at')) {
            $processActivitySupplierPayload['created_at'] = now();
        }

        if (Schema::hasColumn('process_activity_supplier', 'updated_at')) {
            $processActivitySupplierPayload['updated_at'] = now();
        }

        DB::table('process_activity_supplier')->insert($processActivitySupplierPayload);

        DB::table('assets')->insert([
            'name' => 'Vendor portal',
            'supplier_id' => $supplierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agreements')->insert([
            'name' => 'Master service agreement',
            'supplier_id' => $supplierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson('/api/crud/suppliers/'.$supplierId, [
            'tags' => ['Critical'],
        ])->assertOk();

        $tagId = (int) DB::table('tags')->where('name', 'Critical')->value('id');
        $this->assertGreaterThan(0, $tagId);

        $indexResponse = $this->getJson('/api/crud/suppliers?paginate=0&show_my_only=1&%24select=id,name,tags,classified,has_category_issues,process_activities_summary,assets_summary,supplier_categories_summary,agreementscount');
        $indexResponse->assertOk();

        $rows = collect($indexResponse->json());
        $supplier = $rows->firstWhere('id', $supplierId);

        $this->assertNotNull($supplier);
        $this->assertSame(['Critical'], $supplier['tags']);
        $this->assertTrue($supplier['classified']);
        $this->assertTrue($supplier['has_category_issues']);
        $this->assertSame(['Due diligence'], $supplier['process_activities_summary']);
        $this->assertSame(['Vendor portal'], $supplier['assets_summary']);
        $this->assertSame(1, $supplier['agreementscount']);
        $this->assertCount(2, $supplier['supplier_categories_summary']);
        $this->assertTrue((bool) collect($supplier['supplier_categories_summary'])->firstWhere('id', $categoryId)['applicable']);
        $this->assertNull(collect($supplier['supplier_categories_summary'])->firstWhere('id', $secondaryCategoryId)['applicable']);
        $this->assertFalse($rows->contains(fn (array $row): bool => (int) $row['id'] === $completeSupplierId));

        $tagFiltered = $this->getJson('/api/crud/suppliers?paginate=0&tag_id='.$tagId.'&%24select=id,name');
        $tagFiltered->assertOk();
        $this->assertSame([$supplierId], collect($tagFiltered->json())->pluck('id')->all());

        $categoryFiltered = $this->getJson('/api/crud/suppliers?paginate=0&supplier_category_id='.$categoryId.'&%24select=id,name');
        $categoryFiltered->assertOk();
        $this->assertEqualsCanonicalizing([$supplierId, $completeSupplierId], collect($categoryFiltered->json())->pluck('id')->all());

        $issuesOnly = $this->getJson('/api/crud/suppliers?paginate=0&hide_without_issues=1&%24select=id,name,classified,has_category_issues');
        $issuesOnly->assertOk();

        $issueIds = collect($issuesOnly->json())->pluck('id')->all();
        $this->assertContains($supplierId, $issueIds);
        $this->assertContains($issueSupplierId, $issueIds);
        $this->assertNotContains($completeSupplierId, $issueIds);
    }

    public function test_supplier_category_status_endpoint_lists_all_categories_and_upserts_applicability(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Category Reviewer', 'supplier.category@example.com');
        $this->actingAs($user, 'sanctum');

        $supplierId = DB::table('suppliers')->insertGetId([
            'name' => 'Vendor Categories',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstCategoryId = DB::table('supplier_categories')->insertGetId([
            'name' => 'Software',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondCategoryId = DB::table('supplier_categories')->insertGetId([
            'name' => 'Consulting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('supplier_supplier_category')->insert([
            'supplier_id' => $supplierId,
            'supplier_category_id' => $firstCategoryId,
            'applicable' => true,
            'updated_by_name' => $user->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/suppliers/'.$supplierId.'/categories');
        $response->assertOk();

        $rows = collect($response->json('data'));
        $this->assertCount(2, $rows);
        $this->assertTrue((bool) $rows->firstWhere('id', $firstCategoryId)['applicable']);
        $this->assertNull($rows->firstWhere('id', $secondCategoryId)['applicable']);

        $updateResponse = $this->putJson('/api/suppliers/'.$supplierId.'/categories/'.$secondCategoryId, [
            'applicable' => false,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('id', $secondCategoryId)
            ->assertJsonPath('applicable', false)
            ->assertJsonPath('updated_by_name', $user->name);

        $this->assertDatabaseHas('supplier_supplier_category', [
            'supplier_id' => $supplierId,
            'supplier_category_id' => $secondCategoryId,
            'updated_by_name' => $user->name,
            'applicable' => 0,
        ]);
    }

    public function test_supplier_evaluation_endpoint_lists_applicable_requirements_and_updates_assessment(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Evaluation Reviewer', 'supplier.evaluation@example.com');
        $this->actingAs($user, 'sanctum');

        $supplierId = DB::table('suppliers')->insertGetId([
            'name' => 'Vendor Evaluation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $applicableCategoryId = DB::table('supplier_categories')->insertGetId([
            'name' => 'Cloud services',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notApplicableCategoryId = DB::table('supplier_categories')->insertGetId([
            'name' => 'Printing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('supplier_supplier_category')->insert([
            'supplier_id' => $supplierId,
            'supplier_category_id' => $applicableCategoryId,
            'applicable' => true,
            'updated_by_name' => $user->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('supplier_supplier_category')->insert([
            'supplier_id' => $supplierId,
            'supplier_category_id' => $notApplicableCategoryId,
            'applicable' => false,
            'updated_by_name' => $user->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $visibleRequirementId = DB::table('supplier_requirements')->insertGetId([
            'name' => 'Security review',
            'description' => 'Annual security review required',
            'supplier_category_id' => $applicableCategoryId,
            'reassessment' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('supplier_requirements')->insert([
            'name' => 'Paper certificate',
            'description' => 'Only relevant for printing vendors',
            'supplier_category_id' => $notApplicableCategoryId,
            'reassessment' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $listResponse = $this->getJson('/api/suppliers/'.$supplierId.'/evaluation');
        $listResponse->assertOk();

        $rows = collect($listResponse->json('data'));
        $this->assertCount(1, $rows);
        $this->assertSame($visibleRequirementId, $rows[0]['id']);
        $this->assertSame('Security review', $rows[0]['name']);
        $this->assertNull($rows[0]['satisfactory']);
        $this->assertNull($rows[0]['evaluated_at']);

        $updateResponse = $this->putJson('/api/suppliers/'.$supplierId.'/evaluation/'.$visibleRequirementId, [
            'satisfactory' => false,
            'note' => 'Needs follow-up',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('id', $visibleRequirementId)
            ->assertJsonPath('satisfactory', false)
            ->assertJsonPath('note', 'Needs follow-up')
            ->assertJsonPath('evaluated_by_name', $user->name);

        $this->assertDatabaseHas('supplier_supplier_requirement', [
            'supplier_id' => $supplierId,
            'supplier_requirement_id' => $visibleRequirementId,
            'updated_by_name' => $user->name,
            'note' => 'Needs follow-up',
            'satisfactory' => 0,
        ]);

        $reloadedResponse = $this->getJson('/api/suppliers/'.$supplierId.'/evaluation');
        $reloadedResponse->assertOk();

        $reloadedRow = collect($reloadedResponse->json('data'))->firstWhere('id', $visibleRequirementId);
        $this->assertNotNull($reloadedRow);
        $this->assertFalse((bool) $reloadedRow['satisfactory']);
        $this->assertSame('Needs follow-up', $reloadedRow['note']);
        $this->assertSame($user->name, $reloadedRow['evaluated_by_name']);
        $this->assertNotNull($reloadedRow['evaluated_at']);
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


<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SustainabilityAspectsCrudContractTest extends TestCase
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

        if (! Schema::hasTable('processes')) {
            Schema::create('processes', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->boolean('isstartprocess')->default(false);
                $table->boolean('dataprocessor')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sustainability_aspects')) {
            Schema::create('sustainability_aspects', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->bigInteger('threshold')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sustainability_metrics')) {
            Schema::create('sustainability_metrics', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sustainability_metric_levels')) {
            Schema::create('sustainability_metric_levels', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('sustainability_metric_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->bigInteger('multiplier')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sustainability_aspect_sustainability_metric')) {
            Schema::create('sustainability_aspect_sustainability_metric', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('sustainability_aspect_id');
                $table->unsignedBigInteger('sustainability_metric_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('process_sustainability_aspects')) {
            Schema::create('process_sustainability_aspects', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->text('impact_description')->nullable();
                $table->text('monitoring_description')->nullable();
                $table->text('governance_description')->nullable();
                $table->unsignedBigInteger('sustainability_aspect_id');
                $table->unsignedBigInteger('process_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('process_sustainability_aspect_sustainability_metric')) {
            Schema::create('process_sustainability_aspect_sustainability_metric', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('process_sustainability_aspect_id');
                $table->unsignedBigInteger('sustainability_metric_level_id');
                $table->unsignedBigInteger('sustainability_metric_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('objectives')) {
            Schema::create('objectives', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('objective_process_sustainability_aspect')) {
            Schema::create('objective_process_sustainability_aspect', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('process_sustainability_aspect_id');
                $table->unsignedBigInteger('objective_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('process_performance_metrics')) {
            Schema::create('process_performance_metrics', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->boolean('quantitative')->default(false);
                $table->boolean('biggerisbetter')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('process_performance_metric_process_sustainability_aspect')) {
            Schema::create('process_performance_metric_process_sustainability_aspect', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('process_sustainability_aspect_id');
                $table->unsignedBigInteger('process_performance_metric_id');
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
        DB::table('process_performance_metric_process_sustainability_aspect')->truncate();
        DB::table('process_performance_metrics')->truncate();
        DB::table('objective_process_sustainability_aspect')->truncate();
        DB::table('objectives')->truncate();
        DB::table('process_sustainability_aspect_sustainability_metric')->truncate();
        DB::table('process_sustainability_aspects')->truncate();
        DB::table('sustainability_aspect_sustainability_metric')->truncate();
        DB::table('sustainability_metric_levels')->truncate();
        DB::table('sustainability_metrics')->truncate();
        DB::table('sustainability_aspects')->truncate();
        DB::table('processes')->truncate();
        DB::table('departments')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_index_returns_computed_assessment_fields_and_supports_tag_filter(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Sustainability Reader', 'sustainability.reader@example.com');
        $this->actingAs($user, 'sanctum');

        $processId = $this->createProcess('Waste handling');
        $aspectId = $this->createSustainabilityAspect('Environmental impact', 5);

        [$metricA, $metricALow, $metricAHigh] = $this->createMetricWithLevels('Energy usage', 1, 2);
        [$metricB, $metricBLow, $metricBHigh] = $this->createMetricWithLevels('Material reuse', 1, 4);

        DB::table('sustainability_aspect_sustainability_metric')->insert([
            [
                'sustainability_aspect_id' => $aspectId,
                'sustainability_metric_id' => $metricA,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sustainability_aspect_id' => $aspectId,
                'sustainability_metric_id' => $metricB,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $recordId = DB::table('process_sustainability_aspects')->insertGetId([
            'name' => 'Main sustainability aspect',
            'description' => 'Main description',
            'sustainability_aspect_id' => $aspectId,
            'process_id' => $processId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('process_sustainability_aspect_sustainability_metric')->insert([
            [
                'process_sustainability_aspect_id' => $recordId,
                'sustainability_metric_id' => $metricA,
                'sustainability_metric_level_id' => $metricAHigh,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'process_sustainability_aspect_id' => $recordId,
                'sustainability_metric_id' => $metricB,
                'sustainability_metric_level_id' => $metricBHigh,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $tagId = DB::table('tags')->insertGetId([
            'name' => 'Environment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('object_tags')->insert([
            'tag_id' => $tagId,
            'object_tags_id' => $recordId,
            'object_tags_type' => 'App\\Models\\ProcessSustainabilityAspect',
        ]);

        $response = $this->getJson('/api/crud/process_sustainability_aspects?paginate=0&tag_id='.(string) $tagId.'&%24select=id,name,process_id,sustainability_aspect_id');

        $response->assertOk();
        $rows = $response->json();

        $this->assertCount(1, $rows);
        $this->assertSame('Waste handling', $rows[0]['process_name']);
        $this->assertSame('Environmental impact', $rows[0]['sustainability_aspect_name']);
        $this->assertSame(6, $rows[0]['metric_sum']);
        $this->assertTrue($rows[0]['significant']);
        $this->assertSame(['Environment'], $rows[0]['tags']);
        $this->assertCount(2, $rows[0]['sustainability_metrics']);
        $this->assertSame($metricALow, $rows[0]['sustainability_metrics'][0]['levels'][0]['id']);
    }

    public function test_update_syncs_tags_relations_and_metric_assessment_payload(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Sustainability Editor', 'sustainability.editor@example.com');
        $this->actingAs($user, 'sanctum');

        $processId = $this->createProcess('Compliance process');
        $aspectId = $this->createSustainabilityAspect('Governance aspect', 2);

        [$metricId, $_low, $high] = $this->createMetricWithLevels('Supplier traceability', 1, 3);

        DB::table('sustainability_aspect_sustainability_metric')->insert([
            'sustainability_aspect_id' => $aspectId,
            'sustainability_metric_id' => $metricId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recordId = DB::table('process_sustainability_aspects')->insertGetId([
            'name' => 'Editable sustainability aspect',
            'description' => 'Description',
            'sustainability_aspect_id' => $aspectId,
            'process_id' => $processId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $objectiveId = DB::table('objectives')->insertGetId([
            'name' => 'Reduce emissions',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $metricLinkId = DB::table('process_performance_metrics')->insertGetId([
            'name' => 'CO2 intensity',
            'quantitative' => true,
            'biggerisbetter' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->patchJson('/api/crud/process_sustainability_aspects/'.$recordId, [
            'tags' => ['ISO14001', 'Compliance'],
            'objectives' => [$objectiveId],
            'process_performance_metrics' => [$metricLinkId],
            'sustainability_metrics' => [
                $metricId => $high,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('metric_sum', 3)
            ->assertJsonPath('significant', true);

        $this->assertSame(
            2,
            DB::table('object_tags')
                ->where('object_tags_type', 'App\\Models\\ProcessSustainabilityAspect')
                ->where('object_tags_id', $recordId)
                ->count()
        );

        $this->assertDatabaseHas('objective_process_sustainability_aspect', [
            'process_sustainability_aspect_id' => $recordId,
            'objective_id' => $objectiveId,
        ]);

        $this->assertDatabaseHas('process_performance_metric_process_sustainability_aspect', [
            'process_sustainability_aspect_id' => $recordId,
            'process_performance_metric_id' => $metricLinkId,
        ]);

        $this->assertDatabaseHas('process_sustainability_aspect_sustainability_metric', [
            'process_sustainability_aspect_id' => $recordId,
            'sustainability_metric_id' => $metricId,
            'sustainability_metric_level_id' => $high,
        ]);
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

    private function createProcess(string $name): int
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name' => $name.' department '.Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('processes')->insertGetId([
            'name' => $name,
            'department_id' => $departmentId,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSustainabilityAspect(string $name, int $threshold): int
    {
        return DB::table('sustainability_aspects')->insertGetId([
            'name' => $name,
            'threshold' => $threshold,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function createMetricWithLevels(string $name, int $lowMultiplier, int $highMultiplier): array
    {
        $metricId = DB::table('sustainability_metrics')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lowId = DB::table('sustainability_metric_levels')->insertGetId([
            'sustainability_metric_id' => $metricId,
            'name' => 'Low',
            'multiplier' => $lowMultiplier,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $highId = DB::table('sustainability_metric_levels')->insertGetId([
            'sustainability_metric_id' => $metricId,
            'name' => 'High',
            'multiplier' => $highMultiplier,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$metricId, $lowId, $highId];
    }
}


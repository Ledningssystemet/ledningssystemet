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

class ProcessPerformanceCrudContractTest extends TestCase
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

        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('process_performance_metrics')) {
            Schema::create('process_performance_metrics', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->boolean('quantitative')->default(false);
                $table->boolean('biggerisbetter')->default(true);
                $table->string('unit', 30)->nullable();
                $table->string('increment')->nullable();
                $table->bigInteger('minvalue')->nullable();
                $table->bigInteger('maxvalue')->nullable();
                $table->unsignedInteger('precision')->nullable();
                $table->text('postprocessing')->nullable();
                $table->bigInteger('alarm_threshold')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('process_process_performance_metric')) {
            Schema::create('process_process_performance_metric', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('process_performance_metric_id');
                $table->unsignedBigInteger('process_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('process_performance_metric_reports')) {
            Schema::create('process_performance_metric_reports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('process_performance_metric_id');
                $table->unsignedBigInteger('reported_by_id')->nullable();
                $table->bigInteger('value')->nullable();
                $table->unsignedInteger('reportedprecision')->nullable();
                $table->date('reporting_date_at');
                $table->text('comment')->nullable();
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
        DB::table('process_performance_metric_reports')->truncate();
        DB::table('process_process_performance_metric')->truncate();
        DB::table('process_performance_metrics')->truncate();
        DB::table('processes')->truncate();
        DB::table('departments')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_metrics_index_exposes_metric_type_tags_process_links_and_report_count(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Metric Reader', 'metric.reader@example.com');
        $this->actingAs($user, 'sanctum');

        $prefix = 'Metric Index '.Str::lower((string) Str::uuid());
        $processId = $this->createProcess($prefix.' process');

        $metricId = DB::table('process_performance_metrics')->insertGetId([
            'name' => $prefix.' metric',
            'description' => 'Metric description',
            'responsible_user_id' => $user->id,
            'quantitative' => true,
            'biggerisbetter' => false,
            'unit' => '%',
            'precision' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('process_process_performance_metric')->insert([
            'process_performance_metric_id' => $metricId,
            'process_id' => $processId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tagId = DB::table('tags')->insertGetId([
            'name' => 'Operations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('object_tags')->insert([
            'tag_id' => $tagId,
            'object_tags_id' => $metricId,
            'object_tags_type' => 'App\\Models\\ProcessPerformanceMetric',
        ]);

        DB::table('process_performance_metric_reports')->insert([
            'process_performance_metric_id' => $metricId,
            'reported_by_id' => $user->id,
            'value' => 1234,
            'reportedprecision' => 2,
            'reporting_date_at' => '2026-04-10',
            'comment' => 'Measured',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/crud/process_performance_metrics?paginate=0&search='.urlencode($prefix).'&%24select=id,name,responsible_user_id,quantitative,biggerisbetter');

        $response->assertOk();
        $rows = $response->json();

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['metric_type']);
        $this->assertSame([$processId], $rows[0]['process_ids']);
        $this->assertSame(['Operations'], $rows[0]['tags']);
        $this->assertSame(1, $rows[0]['reportcount']);
    }

    public function test_metrics_update_syncs_process_ids_tags_and_metric_type(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Metric Editor', 'metric.editor@example.com');
        $this->actingAs($user, 'sanctum');

        $metricId = DB::table('process_performance_metrics')->insertGetId([
            'name' => 'Editable metric',
            'quantitative' => true,
            'biggerisbetter' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processIds = [
            $this->createProcess('Process A'),
            $this->createProcess('Process B'),
        ];

        $response = $this->patchJson('/api/crud/process_performance_metrics/'.$metricId, [
            'name' => 'Updated metric',
            'metric_type' => 3,
            'process_ids' => $processIds,
            'tags' => ['ISO 9001', 'Quality'],
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'Updated metric')
            ->assertJsonPath('metric_type', 3);

        $savedMetric = DB::table('process_performance_metrics')->where('id', $metricId)->first();
        $this->assertNotNull($savedMetric);
        $this->assertSame(0, (int) $savedMetric->quantitative);

        $linkedProcessIds = DB::table('process_process_performance_metric')
            ->where('process_performance_metric_id', $metricId)
            ->orderBy('process_id')
            ->pluck('process_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        sort($processIds);
        $this->assertSame($processIds, $linkedProcessIds);

        $tagNames = DB::table('tags')
            ->whereIn('name', ['ISO 9001', 'Quality'])
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $this->assertSame(['ISO 9001', 'Quality'], $tagNames);

        $this->assertSame(
            2,
            DB::table('object_tags')
                ->where('object_tags_type', 'App\\Models\\ProcessPerformanceMetric')
                ->where('object_tags_id', $metricId)
                ->count()
        );
    }

    public function test_reports_can_be_created_listed_and_deleted_via_generic_crud(): void
    {
        Gate::before(static fn (): bool => true);

        $user = $this->createUser('Metric Reporter', 'metric.reporter@example.com');
        $this->actingAs($user, 'sanctum');

        $metricId = DB::table('process_performance_metrics')->insertGetId([
            'name' => 'Availability',
            'quantitative' => true,
            'biggerisbetter' => true,
            'precision' => 2,
            'unit' => '%',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->postJson('/api/crud/process_performance_metric_reports', [
            'process_performance_metric_id' => $metricId,
            'reportvalue' => 12.34,
            'reporting_date_at' => '2026-04-15',
            'comment' => 'Monthly report',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('process_performance_metric_id', $metricId)
            ->assertJsonPath('reported_by_name', $user->name)
            ->assertJsonPath('comment', 'Monthly report');

        $reportId = (int) $createResponse->json('id');
        $savedReport = DB::table('process_performance_metric_reports')->where('id', $reportId)->first();

        $this->assertNotNull($savedReport);
        $this->assertSame(1234, (int) $savedReport->value);
        $this->assertSame(2, (int) $savedReport->reportedprecision);
        $this->assertSame($user->id, (int) $savedReport->reported_by_id);

        $indexResponse = $this->getJson('/api/crud/process_performance_metric_reports?paginate=0&sort=-reporting_date_at&filter[process_performance_metric_id]='.(string) $metricId.'&%24select=id,process_performance_metric_id,value,reportedprecision,reporting_date_at,comment,reported_by_id');

        $indexResponse->assertOk();
        $rows = $indexResponse->json();

        $this->assertCount(1, $rows);
        $this->assertSame(12.34, (float) $rows[0]['reportvalue']);
        $this->assertSame(12.34, (float) $rows[0]['calculatedvalue']);
        $this->assertSame($user->name, $rows[0]['reported_by_name']);

        $this->deleteJson('/api/crud/process_performance_metric_reports/'.$reportId)
            ->assertNoContent();

        $this->assertDatabaseMissing('process_performance_metric_reports', ['id' => $reportId]);
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
            'name' => $name.' department',
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
}


<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ledningssystemet\Ledningssystemet\Models\ProjectRisk;
use Ledningssystemet\Ledningssystemet\Models\Risk;
use Tests\TestCase;

class RedesignProjectsMigrationTest extends TestCase
{
    public function testProjectDescriptionsRisksAndActionsAreMigratedToTheirNewModels(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The SQLite PDO driver is not installed.');
        }

        $databaseConfig = config('database');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('sqlite');

        try {
            $this->createLegacySchema();
            $this->seedLegacyRows();

            $migration = require base_path('database/migrations/2026_10_24_081800_redesign_projects_and_project_risks.php');
            $migration->up();

            $this->assertSame("Scope\n\nPurpose", DB::table('projects')->where('id', 1)->value('description'));
            $this->assertFalse(Schema::hasColumn('projects', 'scopedescription'));
            $this->assertFalse(Schema::hasColumn('projects', 'purposedescription'));
            $this->assertFalse(Schema::hasColumn('projects', 'department_id'));
            $this->assertFalse(Schema::hasColumn('risks', 'project_id'));
            $this->assertSame(0, DB::table('risks')->count());

            $risk = DB::table('project_risks')->where('id', 9)->first();
            $this->assertSame('Project risk', $risk->name);
            $this->assertSame(1, $risk->project_id);
            $this->assertSame(1, DB::table('project_risk_controls')->where('project_risk_id', 9)->where('control_id', 3)->count());
            $this->assertSame(ProjectRisk::class, DB::table('activity_log')->where('id', 11)->value('subject_type'));
            $this->assertSame(ProjectRisk::class, DB::table('object_messages')->where('id', 12)->value('object_type'));
            $this->assertSame(ProjectRisk::class, DB::table('object_tags')->where('id', 13)->value('object_tags_type'));
            $projectPropertyId = DB::table('custom_properties')->where('context', ProjectRisk::class)->value('id');
            $this->assertNotNull($projectPropertyId);
            $this->assertSame(Risk::class, DB::table('custom_properties')->where('id', 14)->value('context'));
            $this->assertSame(ProjectRisk::class, DB::table('custom_property_object')->where('id', 15)->value('object_type'));
            $this->assertSame($projectPropertyId, DB::table('custom_property_object')->where('id', 15)->value('custom_property_id'));

            $activity = DB::table('project_activities')->where('project_id', 1)->first();
            $this->assertSame('Follow-up', $activity->name);
            $this->assertSame('2026-12-01', $activity->due);
            $this->assertSame(1, $activity->responsible_user_id);
            $this->assertSame(500, $activity->estimated_cost);
            $this->assertSame(1, DB::table('project_risk_project_activities')->where('project_risk_id', 9)->where('project_activity_id', $activity->id)->count());
            $this->assertSame(0, DB::table('control_actions')->count());
            $this->assertSame(0, DB::table('control_action_mappings')->count());
            $this->assertSame(0, DB::table('risks')->count());
        } finally {
            DB::purge('sqlite');
            config(['database' => $databaseConfig]);
        }
    }

    private function createLegacySchema(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('departments', fn (Blueprint $table) => $table->id());
        Schema::create('controls', fn (Blueprint $table) => $table->id());
        Schema::create('probability_levels', fn (Blueprint $table) => $table->id());
        Schema::create('consequence_levels', fn (Blueprint $table) => $table->id());

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('scopedescription')->nullable();
            $table->text('purposedescription')->nullable();
            $table->unsignedBigInteger('responsible_user_id');
            $table->unsignedBigInteger('department_id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('project_type_id')->nullable();
            $table->foreign('department_id', 'projects_department_id_foreign')->references('id')->on('departments');
        });

        Schema::create('risks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->text('scenariodescription')->nullable();
            $table->text('consequencedescription')->nullable();
            $table->unsignedBigInteger('riskowner_id')->nullable();
            $table->unsignedBigInteger('replacing_id')->nullable();
            $table->unsignedBigInteger('replacedby_id')->nullable();
            $table->dateTime('assessed_at')->nullable();
            $table->dateTime('replaced_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('probability_id')->nullable();
            $table->unsignedBigInteger('consequence_id')->nullable();
            $table->text('assessmentcomment')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('post_probability_id')->nullable();
            $table->unsignedBigInteger('post_consequence_id')->nullable();
            $table->foreign('project_id', 'risks_project_id_foreign')->references('id')->on('projects')->cascadeOnDelete();
        });

        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->date('due');
            $table->unsignedInteger('intervalnum')->default(0);
            $table->string('intervaltype')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('responsible_user_id')->nullable();
        });
        Schema::create('control_risks', function (Blueprint $table): void {
            $table->unsignedBigInteger('risk_id');
            $table->unsignedBigInteger('control_id');
        });
        Schema::create('control_actions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('due')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->unsignedBigInteger('estimated_cost')->nullable();
        });
        Schema::create('control_action_mappings', function (Blueprint $table): void {
            $table->unsignedBigInteger('risk_id');
            $table->unsignedBigInteger('control_action_id');
        });
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('event')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->text('attribute_changes')->nullable();
            $table->text('properties')->nullable();
            $table->timestamps();
        });
        Schema::create('object_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('object_type');
            $table->unsignedBigInteger('object_id');
        });
        Schema::create('object_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('object_tags_type');
            $table->unsignedBigInteger('object_tags_id');
        });
        Schema::create('custom_properties', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('context');
            $table->string('type', 20);
            $table->text('options')->nullable();
            $table->unsignedBigInteger('ordinal')->default(0);
            $table->boolean('display_on_card')->default(false);
            $table->boolean('user_editable')->default(true);
            $table->timestamps();
            $table->boolean('required')->default(false);
        });
        Schema::create('custom_property_object', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('custom_property_id');
            $table->unsignedBigInteger('object_id');
            $table->string('object_type');
            $table->text('value');
            $table->timestamps();
            $table->foreign('custom_property_id')->references('id')->on('custom_properties')->cascadeOnDelete();
        });
    }

    private function seedLegacyRows(): void
    {
        DB::table('users')->insert(['id' => 1]);
        DB::table('departments')->insert(['id' => 2]);
        DB::table('controls')->insert(['id' => 3]);
        DB::table('probability_levels')->insert(['id' => 4]);
        DB::table('consequence_levels')->insert(['id' => 5]);
        DB::table('projects')->insert([
            'id' => 1,
            'name' => 'Project',
            'scopedescription' => 'Scope',
            'purposedescription' => 'Purpose',
            'responsible_user_id' => 1,
            'department_id' => 2,
            'start_date' => '2026-01-01',
        ]);
        DB::table('risks')->insert([
            'id' => 9,
            'name' => 'Project risk',
            'scenariodescription' => 'Scenario',
            'consequencedescription' => 'Consequence',
            'riskowner_id' => 1,
            'created_by' => 1,
            'probability_id' => 4,
            'consequence_id' => 5,
            'project_id' => 1,
        ]);
        DB::table('control_risks')->insert(['risk_id' => 9, 'control_id' => 3]);
        DB::table('control_actions')->insert([
            'id' => 7,
            'name' => 'Follow-up',
            'description' => 'Check the mitigation',
            'due' => '2026-12-01',
            'responsible_id' => 1,
            'estimated_cost' => 500,
        ]);
        DB::table('control_action_mappings')->insert(['risk_id' => 9, 'control_action_id' => 7]);
        DB::table('activity_log')->insert([
            'id' => 11,
            'description' => 'Original risk history',
            'subject_type' => Risk::class,
            'subject_id' => 9,
        ]);
        DB::table('object_messages')->insert([
            'id' => 12,
            'object_type' => Risk::class,
            'object_id' => 9,
        ]);
        DB::table('object_tags')->insert([
            'id' => 13,
            'object_tags_type' => Risk::class,
            'object_tags_id' => 9,
        ]);
        DB::table('custom_properties')->insert([
            'id' => 14,
            'name' => 'Project detail',
            'description' => 'Details preserved during migration',
            'context' => Risk::class,
            'type' => 'string',
        ]);
        DB::table('custom_property_object')->insert([
            'id' => 15,
            'custom_property_id' => 14,
            'object_id' => 9,
            'object_type' => Risk::class,
            'value' => '"Value"',
        ]);
    }
}

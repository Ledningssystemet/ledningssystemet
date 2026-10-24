<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectRenameMigrationTest extends TestCase
{
    public function testProjectRenameMigrationPreservesRowsAndRelationshipsInBothDirections(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
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

            $migration = require base_path('database/migrations/2026_10_24_071500_rename_risk_projects_to_projects.php');
            $migration->up();

            $this->assertTrue(Schema::hasTable('projects'));
            $this->assertTrue(Schema::hasTable('project_types'));
            $this->assertSame(6, DB::table('projects')->where('id', 8)->value('project_type_id'));
            $this->assertSame(8, DB::table('risks')->where('id', 12)->value('project_id'));
            $this->assertSame(8, DB::table('project_user')->where('id', 11)->value('project_id'));
            $this->assertSame(6, DB::table('project_type_risk_templates')->where('id', 9)->value('project_type_id'));
            $this->assertSame(9, DB::table('control_project_type_risk_template')->where('id', 10)->value('project_type_risk_template_id'));
            $this->assertSame('Ledningssystemet\\Ledningssystemet\\Models\\Project', DB::table('activity_log')->where('id', 13)->value('subject_type'));
            $this->assertSame('Ledningssystemet\\Ledningssystemet\\Models\\Project', DB::table('object_messages')->where('id', 14)->value('object_type'));
            $this->assertSame('Ledningssystemet\\Ledningssystemet\\Models\\Project', DB::table('object_tags')->where('id', 15)->value('object_tags_type'));

            $migration->down();

            $this->assertTrue(Schema::hasTable('risk_projects'));
            $this->assertTrue(Schema::hasTable('risk_project_types'));
            $this->assertSame(6, DB::table('risk_projects')->where('id', 8)->value('risk_project_type_id'));
            $this->assertSame(8, DB::table('risks')->where('id', 12)->value('risk_project_id'));
            $this->assertSame(8, DB::table('risk_project_user')->where('id', 11)->value('risk_project_id'));
            $this->assertSame(6, DB::table('risk_project_type_risk_templates')->where('id', 9)->value('risk_project_type_id'));
            $this->assertSame(9, DB::table('control_risk_project_type_risk_template')->where('id', 10)->value('risk_project_type_risk_template_id'));
            $this->assertSame('Ledningssystemet\\Ledningssystemet\\Models\\RiskProject', DB::table('activity_log')->where('id', 13)->value('subject_type'));
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
        Schema::create('risk_project_types', fn (Blueprint $table) => $table->id());

        Schema::create('risk_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('responsible_user_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('risk_project_type_id')->nullable();
            $table->index('department_id', 'risk_projects_department_id_foreign');
            $table->index('responsible_user_id', 'risk_projects_responsible_user_id_foreign');
            $table->index('risk_project_type_id', 'rp_rpti_fk');
            $table->foreign('department_id', 'risk_projects_department_id_foreign')->references('id')->on('departments')->cascadeOnUpdate();
            $table->foreign('responsible_user_id', 'risk_projects_responsible_user_id_foreign')->references('id')->on('users')->cascadeOnUpdate();
            $table->foreign('risk_project_type_id', 'rp_rpti_fk')->references('id')->on('risk_project_types')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('risk_project_type_risk_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('risk_project_type_id')->nullable();
            $table->index('risk_project_type_id', 'rptrt_rpti_fk');
            $table->foreign('risk_project_type_id', 'rptrt_rpti_fk')->references('id')->on('risk_project_types')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('risk_project_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('risk_project_id');
            $table->unsignedBigInteger('user_id');
            $table->index('risk_project_id', 'risk_project_user_risk_project_id_foreign');
            $table->index('user_id', 'risk_project_user_user_id_foreign');
            $table->foreign('risk_project_id', 'risk_project_user_risk_project_id_foreign')->references('id')->on('risk_projects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id', 'risk_project_user_user_id_foreign')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('risks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('risk_project_id')->nullable();
            $table->index('risk_project_id', 'risks_risk_project_id_foreign');
            $table->foreign('risk_project_id', 'risks_risk_project_id_foreign')->references('id')->on('risk_projects')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('control_risk_project_type_risk_template', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('control_id');
            $table->unsignedBigInteger('risk_project_type_risk_template_id');
            $table->index('risk_project_type_risk_template_id', 'crptrt_rptrti');
            $table->foreign('control_id', 'crptrt_ci')->references('id')->on('controls')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('risk_project_type_risk_template_id', 'crptrt_rptrti')->references('id')->on('risk_project_type_risk_templates')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
        });
        Schema::create('object_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('object_type');
        });
        Schema::create('object_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('object_tags_type');
        });
    }

    private function seedLegacyRows(): void
    {
        DB::table('users')->insert(['id' => 4]);
        DB::table('departments')->insert(['id' => 5]);
        DB::table('controls')->insert(['id' => 7]);
        DB::table('risk_project_types')->insert(['id' => 6]);
        DB::table('risk_projects')->insert([
            'id' => 8,
            'responsible_user_id' => 4,
            'department_id' => 5,
            'risk_project_type_id' => 6,
        ]);
        DB::table('risk_project_type_risk_templates')->insert([
            'id' => 9,
            'risk_project_type_id' => 6,
        ]);
        DB::table('risk_project_user')->insert([
            'id' => 11,
            'risk_project_id' => 8,
            'user_id' => 4,
        ]);
        DB::table('risks')->insert([
            'id' => 12,
            'risk_project_id' => 8,
        ]);
        DB::table('control_risk_project_type_risk_template')->insert([
            'id' => 10,
            'control_id' => 7,
            'risk_project_type_risk_template_id' => 9,
        ]);

        $legacyModel = 'Ledningssystemet\\Ledningssystemet\\Models\\RiskProject';
        DB::table('activity_log')->insert(['id' => 13, 'subject_type' => $legacyModel, 'subject_id' => 8]);
        DB::table('object_messages')->insert(['id' => 14, 'object_type' => $legacyModel]);
        DB::table('object_tags')->insert(['id' => 15, 'object_tags_type' => $legacyModel]);
    }
}

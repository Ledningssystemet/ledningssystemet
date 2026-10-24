<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MODEL_RENAMES = [
        'Ledningssystemet\\Ledningssystemet\\Models\\RiskProject' => 'Ledningssystemet\\Ledningssystemet\\Models\\Project',
        'Ledningssystemet\\Ledningssystemet\\Models\\RiskProjectType' => 'Ledningssystemet\\Ledningssystemet\\Models\\ProjectType',
        'Ledningssystemet\\Ledningssystemet\\Models\\RiskProjectTypeRiskTemplate' => 'Ledningssystemet\\Ledningssystemet\\Models\\ProjectTypeRiskTemplate',
    ];

    public function up(): void
    {
        $this->assertLegacySchema();

        $this->dropForeignKeys([
            'risk_project_type_risk_templates' => ['rptrt_rpti_fk'],
            'risk_project_user' => [
                'risk_project_user_risk_project_id_foreign',
                'risk_project_user_user_id_foreign',
            ],
            'risk_projects' => [
                'risk_projects_department_id_foreign',
                'risk_projects_responsible_user_id_foreign',
                'rp_rpti_fk',
            ],
            'risks' => ['risks_risk_project_id_foreign'],
            'control_risk_project_type_risk_template' => ['crptrt_rptrti'],
        ]);

        Schema::rename('risk_project_type_risk_templates', 'project_type_risk_templates');
        Schema::rename('risk_project_types', 'project_types');
        Schema::rename('risk_project_user', 'project_user');
        Schema::rename('risk_projects', 'projects');
        Schema::rename('control_risk_project_type_risk_template', 'control_project_type_risk_template');

        Schema::table('project_type_risk_templates', function (Blueprint $table): void {
            $table->renameColumn('risk_project_type_id', 'project_type_id');
        });
        Schema::table('project_user', function (Blueprint $table): void {
            $table->renameColumn('risk_project_id', 'project_id');
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->renameColumn('risk_project_type_id', 'project_type_id');
        });
        Schema::table('risks', function (Blueprint $table): void {
            $table->renameColumn('risk_project_id', 'project_id');
        });
        Schema::table('control_project_type_risk_template', function (Blueprint $table): void {
            $table->renameColumn('risk_project_type_risk_template_id', 'project_type_risk_template_id');
        });

        $this->renameIndexes([
            'project_user' => [
                'risk_project_user_risk_project_id_foreign' => 'project_user_project_id_foreign',
                'risk_project_user_user_id_foreign' => 'project_user_user_id_foreign',
            ],
            'projects' => [
                'risk_projects_department_id_foreign' => 'projects_department_id_foreign',
                'risk_projects_responsible_user_id_foreign' => 'projects_responsible_user_id_foreign',
            ],
            'risks' => [
                'risks_risk_project_id_foreign' => 'risks_project_id_foreign',
            ],
        ]);

        $this->restoreForeignKeys(true);
        $this->renamePolymorphicReferences(self::MODEL_RENAMES);
    }

    public function down(): void
    {
        $this->assertProjectSchema();

        $this->dropForeignKeys([
            'project_type_risk_templates' => ['rptrt_rpti_fk'],
            'project_user' => [
                'project_user_project_id_foreign',
                'project_user_user_id_foreign',
            ],
            'projects' => [
                'projects_department_id_foreign',
                'projects_responsible_user_id_foreign',
                'rp_rpti_fk',
            ],
            'risks' => ['risks_project_id_foreign'],
            'control_project_type_risk_template' => ['crptrt_rptrti'],
        ]);

        Schema::table('project_type_risk_templates', function (Blueprint $table): void {
            $table->renameColumn('project_type_id', 'risk_project_type_id');
        });
        Schema::table('project_user', function (Blueprint $table): void {
            $table->renameColumn('project_id', 'risk_project_id');
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->renameColumn('project_type_id', 'risk_project_type_id');
        });
        Schema::table('risks', function (Blueprint $table): void {
            $table->renameColumn('project_id', 'risk_project_id');
        });
        Schema::table('control_project_type_risk_template', function (Blueprint $table): void {
            $table->renameColumn('project_type_risk_template_id', 'risk_project_type_risk_template_id');
        });

        $this->renameIndexes([
            'project_user' => [
                'project_user_project_id_foreign' => 'risk_project_user_risk_project_id_foreign',
                'project_user_user_id_foreign' => 'risk_project_user_user_id_foreign',
            ],
            'projects' => [
                'projects_department_id_foreign' => 'risk_projects_department_id_foreign',
                'projects_responsible_user_id_foreign' => 'risk_projects_responsible_user_id_foreign',
            ],
            'risks' => [
                'risks_project_id_foreign' => 'risks_risk_project_id_foreign',
            ],
        ]);

        Schema::rename('project_type_risk_templates', 'risk_project_type_risk_templates');
        Schema::rename('project_types', 'risk_project_types');
        Schema::rename('project_user', 'risk_project_user');
        Schema::rename('projects', 'risk_projects');
        Schema::rename('control_project_type_risk_template', 'control_risk_project_type_risk_template');

        $this->restoreForeignKeys(false);
        $this->renamePolymorphicReferences(array_flip(self::MODEL_RENAMES));
    }

    private function assertLegacySchema(): void
    {
        foreach ([
            'risk_project_types',
            'risk_project_type_risk_templates',
            'risk_project_user',
            'risk_projects',
            'risks',
            'control_risk_project_type_risk_template',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Cannot rename project schema: required table [{$table}] is missing.");
            }
        }
    }

    private function assertProjectSchema(): void
    {
        foreach ([
            'project_types',
            'project_type_risk_templates',
            'project_user',
            'projects',
            'risks',
            'control_project_type_risk_template',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Cannot restore project schema: required table [{$table}] is missing.");
            }
        }
    }

    private function dropForeignKeys(array $foreignKeys): void
    {
        foreach ($foreignKeys as $table => $constraints) {
            Schema::table($table, function (Blueprint $blueprint) use ($constraints): void {
                foreach ($constraints as $constraint) {
                    $blueprint->dropForeign($constraint);
                }
            });
        }
    }

    private function restoreForeignKeys(bool $projectNames): void
    {
        $projectTable = $projectNames ? 'projects' : 'risk_projects';
        $projectTypeTable = $projectNames ? 'project_types' : 'risk_project_types';
        $templateTable = $projectNames ? 'project_type_risk_templates' : 'risk_project_type_risk_templates';
        $memberTable = $projectNames ? 'project_user' : 'risk_project_user';
        $controlTemplateTable = $projectNames
            ? 'control_project_type_risk_template'
            : 'control_risk_project_type_risk_template';
        $projectTypeColumn = $projectNames ? 'project_type_id' : 'risk_project_type_id';
        $projectColumn = $projectNames ? 'project_id' : 'risk_project_id';
        $templateIdColumn = $projectNames
            ? 'project_type_risk_template_id'
            : 'risk_project_type_risk_template_id';

        Schema::table($templateTable, function (Blueprint $table) use ($projectTypeColumn, $projectTypeTable): void {
            $table->foreign($projectTypeColumn, 'rptrt_rpti_fk')
                ->references('id')->on($projectTypeTable)->cascadeOnDelete()->cascadeOnUpdate();
        });
        Schema::table($memberTable, function (Blueprint $table) use ($projectColumn, $projectTable, $projectNames): void {
            $table->foreign($projectColumn, $projectNames ? 'project_user_project_id_foreign' : 'risk_project_user_risk_project_id_foreign')
                ->references('id')->on($projectTable)->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id', $projectNames ? 'project_user_user_id_foreign' : 'risk_project_user_user_id_foreign')
                ->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
        Schema::table($projectTable, function (Blueprint $table) use ($projectTypeColumn, $projectTypeTable, $projectNames): void {
            $table->foreign($projectTypeColumn, 'rp_rpti_fk')
                ->references('id')->on($projectTypeTable)->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('department_id', $projectNames ? 'projects_department_id_foreign' : 'risk_projects_department_id_foreign')
                ->references('id')->on('departments')->cascadeOnUpdate();
            $table->foreign('responsible_user_id', $projectNames ? 'projects_responsible_user_id_foreign' : 'risk_projects_responsible_user_id_foreign')
                ->references('id')->on('users')->cascadeOnUpdate();
        });
        Schema::table('risks', function (Blueprint $table) use ($projectColumn, $projectTable, $projectNames): void {
            $table->foreign($projectColumn, $projectNames ? 'risks_project_id_foreign' : 'risks_risk_project_id_foreign')
                ->references('id')->on($projectTable)->cascadeOnDelete()->cascadeOnUpdate();
        });
        Schema::table($controlTemplateTable, function (Blueprint $table) use ($templateIdColumn, $templateTable): void {
            $table->foreign($templateIdColumn, 'crptrt_rptrti')
                ->references('id')->on($templateTable)->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    private function renameIndexes(array $indexes): void
    {
        foreach ($indexes as $table => $renames) {
            Schema::table($table, function (Blueprint $blueprint) use ($renames): void {
                foreach ($renames as $oldName => $newName) {
                    $blueprint->renameIndex($oldName, $newName);
                }
            });
        }
    }

    private function renamePolymorphicReferences(array $modelRenames): void
    {
        foreach ([
            ['activity_log', 'subject_type'],
            ['activity_log', 'causer_type'],
            ['object_messages', 'object_type'],
            ['object_tags', 'object_tags_type'],
            ['model_has_permissions', 'model_type'],
            ['model_has_roles', 'model_type'],
        ] as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach ($modelRenames as $oldType => $newType) {
                DB::table($table)->where($column, $oldType)->update([$column => $newType]);
            }
        }
    }
};

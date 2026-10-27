<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['activities', 'control_actions', 'project_activities', 'project_risks', 'requirement_sources'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'assessmentcomment')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->text('assessmentcomment')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['activities', 'control_actions', 'project_activities', 'project_risks', 'requirement_sources'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'assessmentcomment')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('assessmentcomment');
                });
            }
        }
    }
};

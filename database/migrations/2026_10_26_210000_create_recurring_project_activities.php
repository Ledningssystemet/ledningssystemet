<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->unsignedInteger('estimated_hours')->nullable();
            $table->string('frequency');
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->json('months');
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->date('last_generated_on')->nullable();
            $table->timestamps();
        });

        Schema::table('project_activities', function (Blueprint $table): void {
            $table->foreignId('recurring_project_activity_id')->nullable()
                ->constrained('recurring_project_activities')->nullOnDelete();
            // NULL values allow completed activities to retain their recurrence history.
            $table->unsignedBigInteger('active_recurring_project_activity_id')->nullable()
                ->virtualAs('CASE WHEN completed_at IS NULL THEN recurring_project_activity_id ELSE NULL END');
            $table->unique('active_recurring_project_activity_id', 'project_activities_one_active_recurrence');
        });
    }

    public function down(): void
    {
        Schema::table('project_activities', function (Blueprint $table): void {
            $table->dropUnique('project_activities_one_active_recurrence');
            $table->dropColumn('active_recurring_project_activity_id');
            $table->dropConstrainedForeignId('recurring_project_activity_id');
        });
        Schema::dropIfExists('recurring_project_activities');
    }
};

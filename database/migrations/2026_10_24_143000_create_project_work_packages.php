<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_work_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_work_package_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_work_package_id');
            $table->unsignedBigInteger('project_activity_id');
            $table->foreign('project_work_package_id', 'pwpa_work_package_foreign')
                ->references('id')->on('project_work_packages')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('project_activity_id', 'pwpa_activity_foreign')
                ->references('id')->on('project_activities')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['project_work_package_id', 'project_activity_id'], 'pwpa_package_activity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_work_package_activities');
        Schema::dropIfExists('project_work_packages');
    }
};

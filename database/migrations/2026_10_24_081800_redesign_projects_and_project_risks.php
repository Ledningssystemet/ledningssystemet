<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ledningssystemet\Ledningssystemet\Models\Activity;
use Ledningssystemet\Ledningssystemet\Models\ActivityLog;
use Ledningssystemet\Ledningssystemet\Models\Control;
use Ledningssystemet\Ledningssystemet\Models\ControlAction;
use Ledningssystemet\Ledningssystemet\Models\ProjectRisk;
use Ledningssystemet\Ledningssystemet\Models\Risk;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('startdate')->nullable();
            $table->date('due')->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->unsignedInteger('estimated_hours')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_risk_project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_risk_id')->constrained('project_risks')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('project_activity_id')->constrained('project_activities')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
         Schema::dropIfExists('project_risk_project_activities');
         Schema::dropIfExists('project_activities');
    }
};

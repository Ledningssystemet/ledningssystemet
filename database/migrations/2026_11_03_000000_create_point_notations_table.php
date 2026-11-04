<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_notations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('point_notation_process', function (Blueprint $table): void {
            $table->foreignId('point_notation_id')->constrained('point_notations')->cascadeOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->primary(['point_notation_id', 'process_id']);
        });

        Schema::create('point_notation_process_activity', function (Blueprint $table): void {
            $table->foreignId('point_notation_id')->constrained('point_notations')->cascadeOnDelete();
            $table->foreignId('process_activity_id')->constrained('process_activities')->cascadeOnDelete();
            $table->primary(['point_notation_id', 'process_activity_id'], 'point_notation_activity_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_notation_process_activity');
        Schema::dropIfExists('point_notation_process');
        Schema::dropIfExists('point_notations');
    }
};

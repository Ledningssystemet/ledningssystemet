<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
   public function up(): void
   {
      Schema::create('project_meeting_agenda_templates', function (Blueprint $table): void {
         $table->id();
         $table->foreignId('project_type_id')->constrained('project_types')->cascadeOnDelete();
         $table->string('name');
         $table->timestamps();
      });

      Schema::create('project_type_meeting_agenda_items', function (Blueprint $table): void {
         $table->id();
         $table->foreignId('agenda_template_id')->constrained('project_meeting_agenda_templates')->cascadeOnDelete()->name('fk_ptmai_ati');
         $table->string('name');
         $table->text('description')->nullable();
         $table->unsignedInteger('ordinal')->default(0);
         $table->timestamps();
         $table->index(['agenda_template_id', 'ordinal'])->name('idx_ptmai_ati');
      });

      Schema::create('project_meetings', function (Blueprint $table): void {
         $table->id();
         $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
         $table->string('name');
         $table->date('date');
         $table->string('location')->nullable();
         $table->json('agenda');
         $table->unsignedInteger('revision')->default(0);
         $table->foreignId('agenda_template_id')->nullable()->constrained('project_meeting_agenda_templates')->nullOnDelete();
         $table->foreignId('previous_meeting_id')->nullable()->constrained('project_meetings')->nullOnDelete();
         $table->timestamp('completed_at')->nullable();
         $table->timestamps();
         $table->index(['project_id', 'date']);
      });

      Schema::create('project_meeting_mentions', function (Blueprint $table): void {
         $table->id();
         $table->foreignId('project_meeting_id')->constrained('project_meetings')->cascadeOnDelete();
         $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
         $table->string('agenda_item_id', 64);
         $table->text('excerpt');
         $table->timestamp('acknowledged_at')->nullable();
         $table->timestamps();
         $table->unique(['project_meeting_id', 'agenda_item_id', 'user_id'], 'pmm_meeting_section_user_unique');
         $table->index(['user_id', 'acknowledged_at']);
      });
   }

   public function down(): void
   {
      Schema::dropIfExists('project_meeting_mentions');
      Schema::dropIfExists('project_meetings');
      Schema::dropIfExists('project_type_meeting_agenda_items');
      Schema::dropIfExists('project_meeting_agenda_templates');
   }
};

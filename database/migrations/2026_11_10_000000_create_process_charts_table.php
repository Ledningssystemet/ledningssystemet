<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       // Add a process_chart column to Processes table
        Schema::table('processes', function (Blueprint $table) {
           $table->json('process_chart_draft')->nullable();
           $table->json('process_chart_published')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_charts');
        Schema::table('processes', function (Blueprint $table) {
            $table->dropColumn('process_chart_draft');
            $table->dropColumn('process_chart_published');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_user', function (Blueprint $table): void {
            $table->boolean('readonly')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table): void {
            $table->dropColumn('readonly');
        });
    }
};

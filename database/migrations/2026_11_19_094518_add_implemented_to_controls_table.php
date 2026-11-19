<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('controls', function (Blueprint $table) {
            $table->boolean('implemented')->default(false);
        });

        // Change the default value of the 'implemented' column to true for existing records
        DB::table('controls')->whereNull('not_applicable_at')->update(['implemented' => true]);
        DB::table('controls')->whereNotNull('not_applicable_at')->update(['implemented' => false]);

        // Also update the reviewed_at column to the current timestamp to updated_at for existing records
        DB::table('controls')->whereNotNull('statusdescription')->whereNotNull('responsible_user_id')->update(['reviewed_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('controls', function (Blueprint $table) {
            $table->dropColumn('implemented');
        });
    }
};

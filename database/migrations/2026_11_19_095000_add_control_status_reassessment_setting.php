<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insert([
            'name' => 'control_status_reassessment_days',
            'scope' => Feature::serializeScope(null),
            'value' => json_encode(365, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('name', 'control_status_reassessment_days')
            ->where('scope', Feature::serializeScope(null))
            ->delete();
    }
};

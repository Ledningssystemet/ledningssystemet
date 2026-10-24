<?php

use Dotenv\Dotenv;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;

return new class extends Migration
{
    public function up(): void
    {
        // Read .env even when Laravel's configuration has already been cached.
        $environment = Dotenv::createArrayBacked(app()->environmentPath(), app()->environmentFile())->safeLoad();
        $defaults = [
            'staff' => false,
            'processes' => false,
            'finding' => false,
            'supplier' => false,
            'gdpr' => false,
            'archival' => true,
        ];
        $settings = [];
        foreach ($defaults as $module => $disabledByDefault) {
            $key = 'DISABLE_'.strtoupper($module);
            $value = env($key, $environment[$key] ?? $disabledByDefault);
            $disabled = match (strtolower((string) $value)) {
                'true', '(true)', '1' => true,
                'false', '(false)', '0', '', 'empty', '(empty)', 'null', '(null)' => false,
                default => throw new InvalidArgumentException("Invalid boolean value for {$key}."),
            };
            $settings[] = [
                'name' => $module,
                'scope' => Feature::serializeScope(null),
                'value' => json_encode(! $disabled, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (['data_privacy_contact_name', 'data_privacy_contact_email'] as $name) {
            $key = 'COMPANY_'.strtoupper($name);
            $settings[] = [
                'name' => $name,
                'scope' => Feature::serializeScope(null),
                'value' => json_encode((string) env($key, $environment[$key] ?? ''), JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('scope');
            $table->text('value');
            $table->timestamps();
            $table->unique(['name', 'scope']);
        });

        DB::table('system_settings')->insert($settings);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

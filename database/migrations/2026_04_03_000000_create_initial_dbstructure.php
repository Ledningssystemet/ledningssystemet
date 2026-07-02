<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Ignore Laravel's own migrations table when deciding if schema bootstrap is needed.
        $tables = collect(DB::select('SHOW TABLES'))
            ->map(static fn ($table): string => (string) array_values((array) $table)[0])
            ->filter(static fn (string $table): bool => $table !== 'migrations')
            ->values();

        if ($tables->isEmpty()) {
            $schemaPath = database_path('schema/baseline_schema.sql');

            if (!is_file($schemaPath)) {
                throw new \RuntimeException("Baseline schema file not found: {$schemaPath}");
            }

            $sql = file_get_contents($schemaPath);

            if ($sql === false || trim($sql) === '') {
                throw new \RuntimeException("Baseline schema file is empty: {$schemaPath}");
            }

            DB::unprepared('SET FOREIGN_KEY_CHECKS=0;');
            DB::unprepared($sql);
            DB::unprepared('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    public function down(): void
    {
        // Drop all tables in the database dynamicaly
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        foreach (DB::select('SHOW TABLES') as $table) {
            // Cnvert the object to an array
            $table = (array) $table;
            $tablename = $table[array_key_first($table)];
            if($tablename == 'migrations')
                continue;

            DB::statement('DROP TABLE IF EXISTS `' . $tablename.'`');
        }

        DB::statement('DROP TABLE IF EXISTS `migrations`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        exit(0);
    }
};


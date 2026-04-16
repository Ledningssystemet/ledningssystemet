<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Check if database tables already exists
        if (DB::select('SHOW TABLES') == []) {
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


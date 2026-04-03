<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $schemaPath = database_path('schema/baseline_schema.sql');

        if (! is_file($schemaPath)) {
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

    public function down(): void
    {
        throw new \RuntimeException('Baseline migration is not reversible. Use migrate:fresh to rebuild the database.');
    }
};


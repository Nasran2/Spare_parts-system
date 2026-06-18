<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE activity_logs MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

            return;
        }

        if ($driver === 'sqlite') {
            return;
        }
    }

    public function down(): void
    {
        // Keep the repaired primary key behavior.
    }
};

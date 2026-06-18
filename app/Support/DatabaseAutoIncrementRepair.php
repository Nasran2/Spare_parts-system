<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseAutoIncrementRepair
{
    public static function repairPrimaryId(string $table): bool
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable($table)) {
            return false;
        }

        $database = config('database.connections.'.config('database.default').'.database');
        $column = DB::selectOne(
            "SELECT COLUMN_TYPE, EXTRA, COLUMN_KEY
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = 'id'",
            [$database, $table]
        );

        if (! $column) {
            return false;
        }

        $safeTable = str_replace('`', '``', $table);
        $primaryKey = DB::selectOne(
            "SELECT COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = 'PRIMARY'
             LIMIT 1",
            [$database, $table]
        );

        if ((string) $column->COLUMN_KEY !== 'PRI' && ! $primaryKey) {
            DB::statement("ALTER TABLE `{$safeTable}` ADD PRIMARY KEY (`id`)");
        }

        if (str_contains(strtolower((string) $column->EXTRA), 'auto_increment')) {
            return false;
        }

        DB::statement("ALTER TABLE `{$safeTable}` MODIFY `id` {$column->COLUMN_TYPE} NOT NULL AUTO_INCREMENT");

        return true;
    }
}

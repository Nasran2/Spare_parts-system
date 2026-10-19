<?php

namespace App\Support\Migrations;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as LaravelSchema;

/** Additive migration operations that can resume after MySQL commits partial DDL. */
class Schema extends LaravelSchema
{
    public const REPAIR_VERSION = '2026-10-18.3';

    private static bool $foreignKeysDeferred = false;

    /** The repair runner installs and verifies final relationships after all tables exist. */
    public static function deferForeignKeys(Closure $callback): mixed
    {
        $previous = self::$foreignKeysDeferred;
        self::$foreignKeysDeferred = true;
        try {
            return $callback();
        } finally {
            self::$foreignKeysDeferred = $previous;
        }
    }

    public static function foreignKeysDeferred(): bool
    {
        return self::$foreignKeysDeferred;
    }

    public static function create($table, Closure $callback): void
    {
        if (! static::hasTable($table)) {
            $blueprint = new ResumableBlueprint(DB::connection(), $table);
            $blueprint->create();
            $callback($blueprint);
            if (DB::connection()->getDriverName() === 'mysql') {
                // Shared hosts may default to MyISAM, which silently ignores FKs.
                $blueprint->engine = 'InnoDB';
            }
            $blueprint->build();

            return;
        }

        static::table($table, $callback);
    }

    public static function table($table, Closure $callback): void
    {
        $blueprint = new ResumableBlueprint(DB::connection(), $table, $callback);
        $blueprint->build();
    }
}

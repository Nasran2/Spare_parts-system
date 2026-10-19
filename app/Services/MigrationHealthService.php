<?php

namespace App\Services;

use App\Support\Migrations\Metadata;
use App\Support\Migrations\ResumableBlueprint;
use App\Support\Migrations\Schema as MigrationSchema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MigrationHealthService
{
    public const REPAIR_VERSION = '2026-10-18.3';

    private array $repairProgress = [];

    private string $migrationOutput = '';

    public function progress(): array
    {
        return ['repairs' => $this->repairProgress, 'output' => $this->migrationOutput];
    }

    public function contract(): array
    {
        $path = database_path('schema/required-schema.json');
        if (! is_file($path)) {
            throw new RuntimeException('Missing database/schema/required-schema.json. Upload the complete application release before migrating.');
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function assertCompleteRelease(): void
    {
        foreach ([MigrationSchema::class, ResumableBlueprint::class] as $component) {
            $version = $component.'::REPAIR_VERSION';
            if (! defined($version) || constant($version) !== self::REPAIR_VERSION) {
                throw new RuntimeException('Incomplete migration-repair upload. Replace the entire app/Support/Migrations folder from the latest repair package before retrying. Required version: '.self::REPAIR_VERSION);
            }
        }
        $expected = $this->contract()['migrations'];
        $missing = array_filter($expected,
            fn ($migration) => ! is_file(database_path('migrations/'.$migration.'.php')));
        if ($missing) {
            throw new RuntimeException("Migration files are missing from this upload. Upload the complete database/migrations folder:\n".implode("\n", $missing));
        }
        $files = array_map(fn ($path) => pathinfo($path, PATHINFO_FILENAME), glob(database_path('migrations/*.php')));
        $unexpected = array_diff($files, $expected);
        if ($unexpected) {
            $schema = DB::connection()->getSchemaBuilder();
            $ran = $schema->hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
            $pending = array_diff($unexpected, $ran);
            if ($pending) {
                throw new RuntimeException("This upload contains unverified pending migrations from a different release. Deploy a matching migration folder and schema contract before running them:\n".implode("\n", $pending));
            }
        }
    }

    /** Read-only: migration history alone does not prove the schema exists. */
    public function check(): array
    {
        $this->assertCompleteRelease();
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $contract = $this->contract();
        $engines = $connection->getDriverName() === 'mysql'
            ? array_column($schema->getTables($connection->getDatabaseName()), 'engine', 'name') : [];
        $issues = [];
        $ran = $schema->hasTable('migrations') ? $connection->table('migrations')->pluck('migration')->all() : [];
        foreach (array_diff($contract['migrations'], $ran) as $migration) {
            $issues[] = 'Pending migration: '.$migration;
        }
        foreach ($contract['tables'] as $table => $expected) {
            if (! $schema->hasTable($table)) {
                $issues[] = 'Missing table: '.$table;

                continue;
            }
            if ($connection->getDriverName() === 'mysql' && strtolower($engines[$table] ?? '') !== 'innodb') {
                $issues[] = 'Unsupported table engine: '.$table.' ('.($engines[$table] ?? 'unknown').'). InnoDB is required for reliable foreign keys.';
            }
            $columnMetadata = $schema->getColumns($table);
            foreach (array_diff(array_keys($expected['columns']), array_column($columnMetadata, 'name')) as $column) {
                $issues[] = 'Missing column: '.$table.'.'.$column;
            }
            if ($connection->getDriverName() === 'mysql') {
                foreach ($columnMetadata as $column) {
                    if (str_contains($expected['columns'][$column['name']] ?? '', 'AUTO_INCREMENT') && ! $column['auto_increment']) {
                        $issues[] = 'Missing AUTO_INCREMENT: '.$table.'.'.$column['name'];
                    }
                }
            }
            $indexes = $schema->getIndexes($table);
            foreach ($expected['indexes'] as $index) {
                // MySQL creates supporting FK indexes automatically; SQLite does not.
                if ($connection->getDriverName() === 'sqlite' && str_ends_with($index['name'], '_foreign')) {
                    continue;
                }
                if (! $this->hasIndex($indexes, $index)) {
                    $issues[] = 'Missing index: '.$table.'.'.$index['name'];
                }
            }
            $foreignKeys = Metadata::foreignKeys($connection, $table);
            foreach ($expected['foreign_keys'] as $key) {
                if (! $this->hasForeignKey($foreignKeys, $key)) {
                    $issues[] = 'Missing or different foreign key: '.$table.'.'.$key['name'];
                }
            }
        }

        return [
            'database' => $connection->getDatabaseName(),
            'migration_count' => count($contract['migrations']),
            'table_count' => count($contract['tables']),
            'issues' => $issues,
            'other_migrations' => array_values(array_diff($ran, $contract['migrations'])),
        ];
    }

    public function migrate(): array
    {
        $this->repairProgress = [];
        $this->migrationOutput = '';
        $this->assertCompleteRelease();
        $lock = fopen(storage_path('framework/migration-repair.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }
            throw new RuntimeException('A migration is already running. Wait for it to finish before retrying.');
        }
        $repairs = &$this->repairProgress;
        try {
            $connection = DB::connection();
            $schema = $connection->getSchemaBuilder();
            $contract = $this->contract();
            if ($connection->getDriverName() === 'mysql') {
                $ran = $schema->hasTable('migrations') ? $connection->table('migrations')->pluck('migration')->all() : [];
                foreach ($contract['tables'] as $table => $expected) {
                    if (in_array($expected['migration'], $ran, true) && ! $schema->hasTable($table)) {
                        // Definitions contain no data and defer foreign keys until all parents exist.
                        $connection->statement($expected['create']);
                        $repairs[] = 'Restored missing table structure: '.$table.'. Previously deleted records require a database backup.';
                    }
                }
                $this->restoreMissingParents($repairs);
                $this->repairMysqlIdsAndEngines($repairs);
            }

            try {
                $run = fn () => Artisan::call('migrate', ['--force' => true, '--no-ansi' => true]);
                $exit = $connection->getDriverName() === 'mysql'
                    ? MigrationSchema::deferForeignKeys($run) : $run();
            } finally {
                $this->migrationOutput = trim(Artisan::output());
            }
            $output = $this->migrationOutput;
            if ($exit !== 0) {
                throw new RuntimeException($output ?: 'Migration command failed.');
            }
            if ($connection->getDriverName() === 'mysql') {
                // Preflight cannot inspect tables that pending migrations have yet
                // to create. Check again before installing any final relationships.
                $this->repairMysqlIdsAndEngines($repairs);
                $this->repairMissingMysqlStructure($repairs);
            }
            $health = $this->check();
            if ($health['issues']) {
                throw new RuntimeException("Database verification failed:\n".implode("\n", $health['issues']));
            }

            return ['output' => $output, 'repairs' => $repairs, 'health' => $health];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function restoreMissingParents(array &$repairs): void
    {
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $tables = $this->contract()['tables'];
        // An imported child can already contain columns from a later migration,
        // even when the migration creating its parent is still pending.
        do {
            $created = false;
            foreach ($tables as $table => $expected) {
                if (! $schema->hasTable($table)) {
                    continue;
                }
                $columns = $schema->getColumnListing($table);
                foreach ($expected['foreign_keys'] as $key) {
                    $parent = $key['foreign_table'];
                    if (array_diff($key['columns'], $columns) || $schema->hasTable($parent)) {
                        continue;
                    }
                    if (! isset($tables[$parent])) {
                        throw new RuntimeException('Missing schema definition for required parent table: '.$parent);
                    }
                    $connection->statement($tables[$parent]['create']);
                    $repairs[] = 'Created missing parent table: '.$parent.' (required by '.$table.'). Previously deleted records require a database backup.';
                    $created = true;
                }
            }
        } while ($created);
    }

    private function repairMissingMysqlStructure(array &$repairs): void
    {
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $tables = $this->contract()['tables'];
        // Finish columns/indexes first; foreign keys may depend on any other table.
        foreach ($tables as $table => $expected) {
            if (! $schema->hasTable($table)) {
                throw new RuntimeException('Missing table after migrations: '.$table.'. Restore its migration or database backup.');
            }
            $columns = $schema->getColumnListing($table);
            foreach ($expected['columns'] as $column => $definition) {
                if (in_array($column, $columns, true)) {
                    continue;
                }
                if (str_contains($definition, 'NOT NULL') && ! str_contains($definition, 'DEFAULT')
                    && $connection->table($table)->exists()) {
                    throw new RuntimeException("Cannot invent values for missing required column {$table}.{$column}. Restore this column's data from a backup before retrying.");
                }
                $connection->statement('ALTER TABLE '.Metadata::quote($table).' ADD '.Metadata::quote($column).' '.$definition);
                $repairs[] = 'Added missing column: '.$table.'.'.$column;
            }
            $indexes = $schema->getIndexes($table);
            foreach ($expected['indexes'] as $index) {
                if ($this->hasIndex($indexes, $index)) {
                    continue;
                }
                if (collect($indexes)->contains('name', $index['name'])) {
                    throw new RuntimeException('Conflicting index: '.$table.'.'.$index['name'].'. Existing index was preserved.');
                }
                $kind = $index['primary'] ? 'PRIMARY KEY' : (($index['unique'] ? 'UNIQUE ' : '').'INDEX '.Metadata::quote($index['name']));
                $names = implode(', ', array_map([Metadata::class, 'quote'], $index['columns']));
                $connection->statement('ALTER TABLE '.Metadata::quote($table).' ADD '.$kind.' ('.$names.')');
                $repairs[] = 'Added missing index: '.$table.'.'.$index['name'];
            }
        }
        foreach ($tables as $table => $expected) {
            $keys = Metadata::foreignKeys($connection, $table);
            foreach ($expected['foreign_keys'] as $key) {
                if ($this->hasForeignKey($keys, $key)) {
                    continue;
                }
                if (collect($keys)->contains('name', $key['name'])) {
                    throw new RuntimeException('Conflicting foreign key: '.$table.'.'.$key['name'].'. Existing relationship was preserved.');
                }
                $query = $connection->table($table.' as child');
                foreach ($key['columns'] as $column) {
                    $query->whereNotNull('child.'.$column);
                }
                $query->whereNotExists(function ($parent) use ($key) {
                    $parent->selectRaw('1')->from($key['foreign_table'].' as parent');
                    foreach ($key['columns'] as $i => $column) {
                        $parent->whereColumn('parent.'.$key['foreign_columns'][$i], 'child.'.$column);
                    }
                });
                $orphans = $query->count();
                if ($orphans) {
                    throw new RuntimeException("Cannot restore {$table}.{$key['name']}: {$orphans} existing rows reference missing {$key['foreign_table']} records. Restore or correct those records, then retry. No records were deleted or reassigned.");
                }
                $columns = implode(', ', array_map([Metadata::class, 'quote'], $key['columns']));
                $foreignColumns = implode(', ', array_map([Metadata::class, 'quote'], $key['foreign_columns']));
                $connection->statement('ALTER TABLE '.Metadata::quote($table).' ADD CONSTRAINT '.Metadata::quote($key['name'])
                    .' FOREIGN KEY ('.$columns.') REFERENCES '.Metadata::quote($key['foreign_table']).' ('.$foreignColumns.')'
                    .' ON DELETE '.strtoupper($key['on_delete']).' ON UPDATE '.strtoupper($key['on_update']));
                $repairs[] = 'Restored foreign key: '.$table.'.'.$key['name'];
            }
        }
    }

    private function repairMysqlIdsAndEngines(array &$repairs): void
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();
        foreach ($connection->select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND ENGINE = 'MyISAM'", [$database]) as $row) {
            if (! isset($this->contract()['tables'][$row->TABLE_NAME]) && $row->TABLE_NAME !== 'migrations') {
                continue;
            }
            $connection->statement('ALTER TABLE '.Metadata::quote($row->TABLE_NAME).' ENGINE=InnoDB');
            $repairs[] = 'Converted to InnoDB: '.$row->TABLE_NAME;
        }
        foreach ($connection->select("SELECT TABLE_NAME, COLUMN_TYPE, EXTRA, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = 'id' AND DATA_TYPE IN ('int','bigint','mediumint','smallint','tinyint')", [$database]) as $row) {
            // Only application-owned ID primary keys; never change unrelated tables.
            if (! isset($this->contract()['tables'][$row->TABLE_NAME]) && $row->TABLE_NAME !== 'migrations') {
                continue;
            }
            if ($row->COLUMN_KEY !== 'PRI') {
                $indexes = $connection->getSchemaBuilder()->getIndexes($row->TABLE_NAME);
                $primary = collect($indexes)->firstWhere('primary', true);
                if ($primary) {
                    throw new RuntimeException('Unexpected primary key on '.$row->TABLE_NAME.'. Restore its original ID primary key before retrying.');
                }
                $duplicate = $connection->table($row->TABLE_NAME)->select('id')->groupBy('id')->havingRaw('COUNT(*) > 1')->exists();
                if ($duplicate || $connection->table($row->TABLE_NAME)->whereNull('id')->exists()) {
                    throw new RuntimeException('Cannot restore primary key on '.$row->TABLE_NAME.': duplicate or empty IDs exist. No records were changed.');
                }
                $connection->statement('ALTER TABLE '.Metadata::quote($row->TABLE_NAME).' ADD PRIMARY KEY (`id`)');
                $repairs[] = 'Restored primary key: '.$row->TABLE_NAME.'.id';
            }
            if (! str_contains(strtolower($row->EXTRA), 'auto_increment')) {
                $connection->statement('ALTER TABLE '.Metadata::quote($row->TABLE_NAME).' MODIFY `id` '.$row->COLUMN_TYPE.' NOT NULL AUTO_INCREMENT');
                $repairs[] = 'Restored AUTO_INCREMENT: '.$row->TABLE_NAME.'.id';
            }
        }
    }

    private function hasIndex(array $indexes, array $expected): bool
    {
        return collect($indexes)->contains(fn ($index) => $index['columns'] === $expected['columns']
            && $index['unique'] === $expected['unique'] && $index['primary'] === $expected['primary']);
    }

    private function hasForeignKey(array $keys, array $expected): bool
    {
        $rule = fn ($value) => in_array(strtolower($value), ['no action', 'restrict'], true) ? 'restrict' : strtolower($value);

        return collect($keys)->contains(fn ($key) => $key['columns'] === $expected['columns']
            && $key['foreign_table'] === $expected['foreign_table'] && $key['foreign_columns'] === $expected['foreign_columns']
            && $rule($key['on_delete']) === $rule($expected['on_delete'])
            && $rule($key['on_update']) === $rule($expected['on_update']));
    }
}

<?php

if (! in_array('--write', $argv, true)) {
    fwrite(STDERR, "Usage: php scripts/build-migration-schema.php --write\nCreates a disposable local MySQL database to rebuild the release schema contract.\n");
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$original = config('database.default');
$config = DB::connection()->getConfig();
if (! in_array($config['host'] ?? '', ['localhost', '127.0.0.1', '::1']) || DB::connection()->getDriverName() !== 'mysql') {
    throw new RuntimeException('Local MySQL only');
}
if (! empty($config['read']) || ! empty($config['write'])) {
    throw new RuntimeException('Schema generation requires a single local database connection, without read/write overrides.');
}
$name = 'spare_parts_migration_qa_'.date('YmdHis').'_'.bin2hex(random_bytes(3));
DB::statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$guardActive = true;
DB::connection($original)->beforeExecuting(function () use (&$guardActive) {
    if ($guardActive) {
        throw new RuntimeException('Schema generation refused a query on the original database connection.');
    }
});
try {
    $config['database'] = $name;
    // A connection URL must never override the generated disposable database.
    $config['url'] = null;
    // Laravel uses this name to choose the default connection inside each migration.
    unset($config['name']);
    config(['database.connections.migration_qa' => $config, 'database.default' => 'migration_qa']);
    DB::setDefaultConnection('migration_qa');
    Schema::clearResolvedInstance('db.schema');
    if (DB::connection()->getName() !== 'migration_qa' || DB::selectOne('SELECT DATABASE() AS name')->name !== $name) {
        throw new RuntimeException('Disposable schema connection verification failed.');
    }
    $exit = Artisan::call('migrate', ['--database' => 'migration_qa', '--force' => true, '--no-ansi' => true]);
    echo Artisan::output();
    if ($exit) {
        throw new RuntimeException('Fresh migrations failed');
    }
    $connection = DB::connection();
    $schema = $connection->getSchemaBuilder();
    $manifest = ['migrations' => [], 'tables' => []];
    $creators = [];
    foreach (glob(database_path('migrations/*.php')) as $path) {
        $migration = pathinfo($path, PATHINFO_FILENAME);
        $manifest['migrations'][] = $migration;
        preg_match_all("/Schema::create\('([^']+)'/", file_get_contents($path), $matches);
        foreach ($matches[1] as $table) {
            $creators[$table] = $migration;
        }
    }
    foreach ($schema->getTableListing($name, false) as $table) {
        if ($table === 'migrations') {
            continue;
        }
        $row = (array) $connection->selectOne('SHOW CREATE TABLE `'.$table.'`');
        $sql = array_values($row)[1];
        $definitions = [];
        foreach (explode("\n", $sql) as $line) {
            if (preg_match('/^  `([^`]+)` (.*?)(?:,)?$/', $line, $m)) {
                $definitions[$m[1]] = rtrim($m[2], ',');
            }
        }
        $sql = preg_replace('/^  CONSTRAINT .*\n/m', '', $sql);
        $sql = preg_replace('/,\n\)/', "\n)", $sql);
        $sql = preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql);
        $manifest['tables'][$table] = ['migration' => $creators[$table] ?? null, 'create' => $sql, 'columns' => $definitions, 'indexes' => $schema->getIndexes($table), 'foreign_keys' => App\Support\Migrations\Metadata::foreignKeys($connection, $table)];
    }
    file_put_contents(database_path('schema/required-schema.json'), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    echo 'Contract generated: '.count($manifest['tables']).' tables, '.count($manifest['migrations'])." migrations\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    $failed = true;
} finally {
    $guardActive = false;
    DB::disconnect('migration_qa');
    config(['database.default' => $original]);
    DB::setDefaultConnection($original);
    Schema::clearResolvedInstance('db.schema');
    DB::connection($original)->statement('DROP DATABASE `'.$name.'`');
    echo "Disposable QA database removed\n";
}
exit(isset($failed) ? 1 : 0);

<?php

namespace App\Console\Commands;

use App\Services\MigrationHealthService;
use Illuminate\Console\Command;
use Throwable;

class CheckDatabase extends Command
{
    protected $signature = 'database:check {--repair : Run pending migrations and repair missing schema without deleting existing data}';

    protected $description = 'Verify the deployed migration files, tables, columns, indexes and foreign keys';

    public function handle(MigrationHealthService $service): int
    {
        try {
            if ($this->option('repair')) {
                $result = $service->migrate();
                $this->line($result['output']);
                foreach ($result['repairs'] as $repair) {
                    $this->line($repair);
                }
                $health = $result['health'];
            } else {
                $health = $service->check();
            }
            $this->line('Database: '.$health['database']);
            foreach ($health['issues'] as $issue) {
                $this->error($issue);
            }
            if ($health['other_migrations']) {
                $this->warn('Additional migration history from another release: '.implode(', ', $health['other_migrations']));
            }
            if ($health['issues']) {
                return self::FAILURE;
            }
            $this->info("Verified {$health['migration_count']} migrations and {$health['table_count']} application tables, including required columns, indexes and foreign keys.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($this->option('repair')) {
                $progress = $service->progress();
                foreach ($progress['repairs'] as $repair) {
                    $this->line($repair);
                }
                $this->line($progress['output']);
            }
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

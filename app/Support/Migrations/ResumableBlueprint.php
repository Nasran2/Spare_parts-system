<?php

namespace App\Support\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

class ResumableBlueprint extends Blueprint
{
    public const REPAIR_VERSION = '2026-10-18.3';

    protected function addImpliedCommands()
    {
        if (Schema::foreignKeysDeferred()) {
            $this->commands = array_values(array_filter($this->commands,
                fn ($command) => $command->name !== 'foreign'));
        }
        if ($this->creating()) {
            parent::addImpliedCommands();

            return;
        }
        $schema = $this->connection->getSchemaBuilder();
        $columns = $schema->getColumnListing($this->table);
        // Extract fluent indexes before filtering existing column definitions.
        $this->addFluentIndexes();
        $indexes = $schema->getIndexes($this->table);
        $foreignKeys = Metadata::foreignKeys($this->connection, $this->table);

        $this->columns = array_values(array_filter($this->columns,
            fn ($column) => $column->change || ! in_array($column->name, $columns, true)));

        $this->commands = array_values(array_filter($this->commands, function ($command) use ($columns, &$indexes, &$foreignKeys) {
            if ($command instanceof ColumnDefinition) {
                return $command->change || ! in_array($command->name, $columns, true);
            }

            if ($command->name === 'foreign') {
                foreach ($foreignKeys as $key) {
                    if ($key['columns'] === (array) $command->columns
                        && $key['foreign_table'] === $command->on
                        && $key['foreign_columns'] === (array) $command->references) {
                        return false;
                    }
                }
            }

            if ($command->name === 'dropForeign') {
                $found = false;
                $foreignKeys = array_values(array_filter($foreignKeys, function ($key) use ($command, &$found) {
                    // SQLite identifies FKs by columns and has no constraint names.
                    $name = $key['name'] ?? $this->createIndexName('foreign', $key['columns']);
                    if ($name === $command->index) {
                        $found = true;

                        return false;
                    }

                    return true;
                }));

                return $found || $this->connection->getDriverName() !== 'mysql';
            }

            if (in_array($command->name, ['primary', 'unique', 'index', 'fulltext', 'spatialIndex'], true)) {
                foreach ($indexes as $index) {
                    if ($index['name'] === $command->index
                        || ($index['columns'] === (array) $command->columns
                            && ($command->name !== 'unique' || $index['unique'])
                            && ($command->name !== 'primary' || $index['primary']))) {
                        return false;
                    }
                }
            }

            return true;
        }));

        parent::addImpliedCommands();
    }
}

<?php

namespace App\Support\Migrations;

use Illuminate\Database\Connection;

class Metadata
{
    public static function foreignKeys(Connection $connection, string $table): array
    {
        if ($connection->getDriverName() !== 'mysql') {
            return $connection->getSchemaBuilder()->getForeignKeys($table);
        }

        // SHOW CREATE avoids expensive information_schema joins on shared MySQL hosts.
        $row = (array) $connection->selectOne('SHOW CREATE TABLE '.static::quote($connection->getTablePrefix().$table));
        $sql = array_values($row)[1];
        preg_match_all('/CONSTRAINT `([^`]+)` FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)([^,\n]*)/', $sql, $matches, PREG_SET_ORDER);

        return array_map(function ($match) {
            preg_match('/ON DELETE ([A-Z ]+?)(?: ON UPDATE|$)/', trim($match[5]), $delete);
            preg_match('/ON UPDATE ([A-Z ]+)$/', trim($match[5]), $update);

            return [
                'name' => $match[1],
                'columns' => static::columnNames($match[2]),
                'foreign_table' => $match[3],
                'foreign_columns' => static::columnNames($match[4]),
                'on_delete' => strtolower($delete[1] ?? 'restrict'),
                'on_update' => strtolower($update[1] ?? 'restrict'),
            ];
        }, $matches);
    }

    public static function quote(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    private static function columnNames(string $list): array
    {
        preg_match_all('/`([^`]+)`/', $list, $matches);

        return $matches[1];
    }
}

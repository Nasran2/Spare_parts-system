<?php

namespace App\Support;

final class SafeCsv
{
    public static function write($stream, array $row): int|false
    {
        $row = array_map(static function ($value) {
            // Quoting CSV fields alone does not stop spreadsheet formula execution.
            if (is_string($value) && preg_match('/^[\x00-\x20]*[=+@-]/', $value) && ! is_numeric($value)) {
                return "'".$value;
            }

            return $value;
        }, $row);

        return fputcsv($stream, $row);
    }
}

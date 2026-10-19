<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BusinessWorkbook implements WithMultipleSheets
{
    public function __construct(private array $tables, private array $metadata) {}

    public function sheets(): array
    {
        return array_map(fn ($table, $index) => new BusinessWorksheet($table, $this->metadata, $index + 1), $this->tables, array_keys($this->tables));
    }
}

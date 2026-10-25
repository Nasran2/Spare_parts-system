<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TaxReportExport extends \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder implements FromCollection, WithHeadings, \Maatwebsite\Excel\Concerns\WithCustomValueBinder
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $headings
    ) {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function bindValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell, $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    public function headings(): array
    {
        return $this->headings;
    }
}

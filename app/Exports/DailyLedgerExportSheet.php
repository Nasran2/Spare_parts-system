<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyLedgerExportSheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(private readonly array $section) {}

    public function array(): array
    {
        return $this->section['rows'];
    }

    public function title(): string
    {
        return $this->section['title'];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Keep references/leading zeroes literal, including text starting with =, + or -.
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        $header = $this->section['headerRow'];
        $sheet->freezePane('A'.($header + 1));
        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setWidth(19);
        }
        $sheet->getColumnDimension('A')->setWidth(16);
        if ($this->section['key'] === 'sales') {
            $sheet->getColumnDimension('B')->setWidth(26);
            $sheet->getColumnDimension('C')->setWidth(28);
            $sheet->getColumnDimension('I')->setWidth(42);
            $sheet->getStyle('D'.($header + 1).':H'.$sheet->getHighestRow())->getNumberFormat()->setFormatCode('#,##0.00');
        } else {
            $sheet->getColumnDimension('F')->setWidth(60);
            $sheet->getStyle('G'.($header + 1).':I'.$sheet->getHighestRow())->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sheet->getStyle('A'.($header + 1).':I'.$sheet->getHighestRow())->getAlignment()->setWrapText(true)->setVertical('top');
        $sheet->getStyle('B5:B'.($this->section['key'] === 'sales' ? '9' : '10'))->getNumberFormat()->setFormatCode('#,##0.00');

        return [1 => ['font' => ['bold' => true, 'size' => 14]], $header => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '334155']]], $sheet->getHighestRow() => ['font' => ['bold' => true]]];
    }
}

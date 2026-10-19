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

class BusinessWorksheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(private array $table, private array $metadata, private int $index) {}

    public function title(): string
    {
        $title = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $this->table['title']);

        return mb_substr($this->index.' '.$title, 0, 31);
    }

    public function array(): array
    {
        return [[$this->metadata['title'].(isset($this->metadata['business']) ? ' — '.$this->metadata['business'] : '')], [$this->metadata['period']], ['Generated: '.$this->metadata['generated']], [$this->metadata['filters']], $this->table['headers'], ...$this->table['rows']];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_int($value) || is_float($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
        } else {
            // Never execute formulas from names, descriptions, invoice or bank references.
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
        }

        return true;
    }

    public function styles(Worksheet $sheet): array
    {
        foreach (range(1, 4) as $row) {
            if (count($this->table['headers']) > 1) {
                $sheet->mergeCells('A'.$row.':'.$sheet->getHighestDataColumn().$row);
            }
            $sheet->getRowDimension($row)->setRowHeight($row === 4 ? 36 : 24);
        }
        $sheet->freezePane('A6');
        if ($sheet->getHighestDataRow() > 5) {
            $sheet->setAutoFilter('A5:'.$sheet->getHighestDataColumn().$sheet->getHighestDataRow());
        }
        foreach ($sheet->getColumnIterator() as $column) {
            $letter = $column->getColumnIndex();
            $sheet->getColumnDimension($letter)->setAutoSize(false)->setWidth(24);
        }
        $sheet->getStyle('A1:'.$sheet->getHighestDataColumn().$sheet->getHighestDataRow())->getAlignment()->setWrapText(true);
        foreach ($this->table['rows'] as $index => $row) {
            foreach ($row as $column => $value) {
                if (is_int($value) || is_float($value)) {
                    $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column + 1).($index + 6);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
                }
            }
        }

        return [1 => ['font' => ['bold' => true, 'size' => 16]], 5 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E40AF']]]];
    }
}

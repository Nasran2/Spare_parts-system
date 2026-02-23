<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet as PhpWorksheet;

class ProductImportTemplateExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
{
    use Exportable;

    private array $categories;
    private array $brands;
    private array $units;

    public function __construct(array $categories = [], array $brands = [], array $units = [])
    {
        $this->categories = $categories ?: ['Please create categories first'];
        $this->brands = $brands ?: ['Please create brands first'];
        $this->units = $units ?: ['Please create units first'];
    }

    public function collection()
    {
        return new Collection([]);
    }

    public function headings(): array
    {
        return [
            'Name',
            'SKU',
            'Category Names',
            'Brand Name',
            'Unit Short Name',
            'Cost Price',
            'Cost Code',
            'Selling Price',
            'Profit Margin %',
            'Profit Margin Fixed',
            'Stock Quantity',
            'Alert Quantity',
            'Description',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $spreadsheet = $sheet->getParent();

                $listSheet = $spreadsheet->createSheet();
                $listSheet->setTitle('Lists');
                $listSheet->setSheetState(PhpWorksheet::SHEETSTATE_VERYHIDDEN);

                $categoryCount = $this->populateColumn($listSheet, 'A', $this->categories);
                $brandCount = $this->populateColumn($listSheet, 'B', $this->brands);
                $unitCount = $this->populateColumn($listSheet, 'C', $this->units);

                $this->applyValidation($sheet, 'C', "'Lists'!\$A\$1:\$A\$" . $categoryCount);
                $this->applyValidation($sheet, 'D', "'Lists'!\$B\$1:\$B\$" . $brandCount);
                $this->applyValidation($sheet, 'E', "'Lists'!\$C\$1:\$C\$" . $unitCount);
                $this->applyProfitMarginFormulas($sheet, 2, 500);
            },
        ];
    }

    private function populateColumn(PhpWorksheet $sheet, string $column, array $values): int
    {
        $row = 1;
        foreach ($values as $value) {
            $sheet->setCellValue("{$column}{$row}", $value);
            $row++;
        }

        return max(1, $row - 1);
    }

    private function applyValidation(PhpWorksheet $sheet, string $column, string $formula)
    {
        for ($row = 2; $row <= 500; $row++) {
            $validation = $sheet->getCell("{$column}{$row}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setFormula1($formula);
        }
    }
    
    private function applyProfitMarginFormulas(PhpWorksheet $sheet, int $startRow, int $endRow)
    {
        for ($row = $startRow; $row <= $endRow; $row++) {
            $percentageFormula = "=IF(AND(ISNUMBER(\$F{$row}),ISNUMBER(\$H{$row}),\$F{$row}<>0),(\$H{$row}-\$F{$row})/\$F{$row}*100,\"\")";
            $fixedFormula = "=IF(AND(ISNUMBER(\$F{$row}),ISNUMBER(\$H{$row})),\$H{$row}-\$F{$row},\"\")";
            $sheet->setCellValue("I{$row}", $percentageFormula);
            $sheet->setCellValue("J{$row}", $fixedFormula);
        }
    }
}

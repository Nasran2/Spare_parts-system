<?php

namespace App\Exports;

use App\Services\PrivacyModeService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DailyLedgerExport implements WithMultipleSheets
{
    public function __construct(private readonly array $data) {}

    public function sheets(): array
    {
        return array_map(fn ($section) => new DailyLedgerExportSheet($section), self::sections($this->data));
    }

    public static function sections(array $data): array
    {
        $masked = PrivacyModeService::isActiveForUser(auth()->user()) && PrivacyModeService::shouldMaskForCurrentPage();
        $money = fn ($value) => $masked ? PrivacyModeService::maskAmount((float) $value) : (float) $value;
        $result = [];
        foreach ($data['selectedSections'] as $key) {
            $rows = [[$data['sectionLabels'][$key]], ['From Date', $data['from'], 'To Date', $data['to']], ['Currency', $data['currency']], ['']];
            if ($key === 'sales') {
                foreach (['cash' => 'Total Cash Sales', 'cheque' => 'Total Cheque Sales', 'credit' => 'Credit Sales', 'other' => 'Bank / Other Payments', 'total' => 'Total Sales Value'] as $field => $label) {
                    $rows[] = [$label, $money($data['salesTotals'][$field])];
                }
                $rows[] = ['Total Transactions', $data['salesTotals']['count']];
                $rows[] = ['Sales dated within the selected period; settlement and credit are shown as at To Date. Cheques include held and cleared cheques once.'];
                $rows[] = [''];
                $headers = ['Date', 'Invoice', 'Customer', 'Cash', 'Cheque', 'Bank / Other', 'Credit', 'Total', 'Notes'];
                $headerRow = count($rows) + 1;
                $rows[] = $headers;
                foreach ($data['salesRows'] as $row) {
                    $rows[] = [$row['date'], $row['invoice'], $row['customer'], $money($row['cash']), $money($row['cheque']), $money($row['other']), $money($row['credit']), $money($row['total']), $row['notes']];
                }
                $rows[] = ['Period Totals', '', '', $money($data['salesTotals']['cash']), $money($data['salesTotals']['cheque']), $money($data['salesTotals']['other']), $money($data['salesTotals']['credit']), $money($data['salesTotals']['total'])];
            } else {
                $section = $data['ledgerSections'][$key];
                foreach (['opening' => 'Balance Brought Forward', 'in' => 'Money In', 'out' => 'Money Out', 'closing' => 'Balance Carried Forward', 'revenue' => 'Sales-Related Receipts / Cheque Holds', 'expenses' => 'Period Expenses'] as $field => $label) {
                    $rows[] = [$label, $money($section[$field])];
                }
                $rows[] = [''];
                $headers = ['Date', 'Type', 'Account', 'Related Account', 'Reference', 'Description & Details', 'Money In', 'Money Out', 'Balance'];
                $headerRow = count($rows) + 1;
                $rows[] = $headers;
                $rows[] = [$data['from'], 'Balance', '', '', '', 'Balance Brought Forward', '', '', $money($section['opening'])];
                foreach ($section['rows'] as $row) {
                    $rows[] = [$row['date'], $row['type'], $row['account'], $row['related'], $row['reference'], $row['description'], $money($row['in']), $money($row['out']), $money($row['balance'])];
                }
                $rows[] = [$data['to'], 'Balance', '', '', '', 'Balance Carried Forward', $money($section['in']), $money($section['out']), $money($section['closing'])];
            }
            $result[] = ['key' => $key, 'title' => match ($key) {
                'main' => 'Main Cash', 'cheques' => 'Customer Cheques', 'petty' => 'Petty Cash', default => 'Sales Transactions'
            }, 'rows' => $rows, 'headerRow' => $headerRow];
        }

        return $result;
    }
}

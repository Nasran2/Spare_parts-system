<?php
use Illuminate\Support\Facades\DB;

DB::statement('SET FOREIGN_KEY_CHECKS=0;');

$tables = [
    'sales',
    'sale_items',
    'sale_returns',
    'sale_return_items',
    'purchases',
    'purchase_items',
    'purchase_returns',
    'purchase_return_items',
    'products',
    'product_prices',
    'store_stocks',
    'product_tax_settings',
    'product_store_exclusions',
    'account_transactions',
    'payments',
    'transaction_tax_lines',
    'quotations',
    'quotation_items',
    'stock_shipments',
    'stock_shipment_items',
    'stock_shipment_allocations',
    'store_transfers',
    'store_stock_transfers',
    'tax_ledger_entries',
    'tax_payments',
];

foreach($tables as $table) {
    try {
        DB::table($table)->truncate();
        echo "Truncated $table\n";
    } catch (\Exception $e) {
        echo "Skipped $table or error: " . $e->getMessage() . "\n";
    }
}

DB::statement('SET FOREIGN_KEY_CHECKS=1;');
echo "Data reset successfully.\n";

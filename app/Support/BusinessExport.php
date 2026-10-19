<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** Explicit allowlist of business lists whose marked tables can be downloaded. */
final class BusinessExport
{
    public const PAGES = [
        'products.index' => ['products', 'Products'],
        'purchases.index' => ['purchases', 'Purchases'],
        'sales.index' => ['sales', 'Sales'],
        'quotations.index' => ['quotations', 'Quotations'],
        'customers.index' => ['customers', 'Customers'],
        'suppliers.index' => ['suppliers', 'Suppliers'],
        'expenses.index' => ['expenses', 'Expenses'],
        'sale-returns.index' => ['sale-returns', 'Sales Returns'],
        'purchase-returns.index' => ['purchase-returns', 'Purchase Returns'],
        'preorders.index' => ['preorders', 'Preorders'],
        'preorders.report' => ['preorder-report', 'Preorder Report'],
        'preorders.customer-report' => ['preorder-customers', 'Customer Preorder Report'],
        'accounting.accounts' => ['chart-accounts', 'Chart of Accounts'],
        'accounting.transactions' => ['account-transactions', 'Account Transactions'],
        'accounting.banks' => ['bank-reconciliation', 'Bank Reconciliation'],
        'accounting.bank-balances' => ['bank-balances', 'Bank Balances and History'],
        'accounting.ledger' => ['account-ledger', 'Account Ledger'],
        'accounting.cash-book' => ['cash-book', 'Cash Book'],
        'accounting.bank-book' => ['bank-book', 'Bank Book'],
        'accounting.trial-balance' => ['trial-balance', 'Trial Balance'],
        'accounting.balance-sheet' => ['balance-sheet', 'Balance Sheet'],
        'accounting.t-accounts' => ['t-accounts', 'T Accounts'],
        'accounting.owner-equity' => ['owner-equity', 'Owner Capital and Drawings'],
        'reports.sales' => ['sales-report', 'Sales Report'],
        'reports.purchase' => ['purchase-report', 'Purchase Report'],
        'reports.stock' => ['stock-report', 'Stock Report'],
        'reports.expense' => ['expense-report', 'Expense Report'],
        'reports.profit-loss' => ['profit-loss', 'Profit and Loss'],
        'reports.vat' => ['vat-report', 'VAT Report'],
        'reports.receive' => ['receipts-report', 'Receipts Report'],
        'reports.debit' => ['payments-report', 'Payments Report'],
        'reports.due-bills' => ['due-bills', 'Due Bills'],
        'reports.customer-due' => ['customer-due', 'Customer Balances'],
        'reports.trending' => ['trending-products', 'Top Selling Products'],
        'reports.never-sold' => ['never-sold', 'Never Sold Products'],
        'reports.unsold-recently' => ['unsold-products', 'Unsold Products'],
    ];

    public static function page(?string $route): ?array
    {
        return self::PAGES[$route === 'preorders.status' ? 'preorders.index' : ($route ?? '')] ?? null;
    }

    public static function perPage(Builder $query, int $default): int
    {
        return request()->attributes->get('business_export') ? max(1, (clone $query)->count()) : $default;
    }
}

@if(request()->attributes->get('business_export'))
@if(request()->routeIs('suppliers.index'))
<table data-export-table data-export-title="Suppliers"><thead><tr><th>Name</th><th>Company</th><th>Phone</th><th>Email</th><th>Address</th><th>Purchases</th><th>Balance Due</th><th>Status</th></tr></thead><tbody>
@foreach($suppliers as $supplier)<tr><td>{{ !empty($controls['hide_supplier_names']) ? 'Hidden Supplier' : $supplier->name }}</td><td>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : $supplier->company_name }}</td><td>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : $supplier->phone }}</td><td>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : $supplier->email }}</td><td>{{ !empty($controls['hide_supplier_names']) ? 'Hidden' : $supplier->address }}</td><td>{{ $supplier->purchases()->count() }}</td><td>{{ number_format($supplier->due_amount, 2) }}</td><td>{{ $supplier->is_active ? 'Active' : 'Inactive' }}</td></tr>@endforeach
</tbody></table>
@endif
@if(request()->routeIs('reports.profit-loss'))
<table data-export-table data-export-title="Profit and Loss"><thead><tr><th>Description</th><th>Amount</th></tr></thead><tbody>
<tr><td>Sales Revenue</td><td>{{ $maskMoney($summary['sales_revenue'], !empty($controls['hide_total_sales'])) }}</td></tr>
<tr><td>Cost of Goods Sold</td><td>{{ $maskMoney($summary['cogs'], !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</td></tr>
<tr><td>Gross Profit</td><td>{{ $maskProfitMoney($summary['gross_profit'], !empty($controls['hide_profit_loss'])) }}</td></tr>
<tr><td>Expenses</td><td>{{ $maskMoney($summary['expenses']) }}</td></tr>
<tr><td>Net Profit</td><td>{{ $maskProfitMoney($summary['net_profit'], !empty($controls['hide_profit_loss'])) }}</td></tr>
</tbody></table>
@endif
@if(request()->routeIs('accounting.transactions'))
<table data-export-table data-export-title="Account Transactions"><thead><tr><th>Date</th><th>Account</th><th>Related Account</th><th>Direction</th><th>Method</th><th>Bank</th><th>Reference</th><th>Description</th><th>Amount</th></tr></thead><tbody>
@foreach($transactions as $entry)<tr><td>{{ $entry->transaction_date->toDateString() }}</td><td>{{ $entry->account?->name }}</td><td>{{ $entry->relatedAccount?->name }}</td><td>{{ $entry->direction === 'in' ? 'Money In' : 'Money Out' }}</td><td>{{ str_replace('_', ' ', $entry->payment_method) }}</td><td>{{ $entry->bankAccount?->bank_name }}</td><td>{{ $entry->reference_no }}</td><td>{{ $entry->description }}</td><td>{{ number_format($entry->amount, 2) }}</td></tr>@endforeach
</tbody></table>
@endif
@if(request()->routeIs('accounting.banks'))
<table data-export-table data-export-title="Bank Accounts"><thead><tr><th>Bank</th><th>Account Name</th><th>Account Number</th><th>Opening Balance</th><th>Current Balance</th></tr></thead><tbody>
@foreach($banks as $item)<tr><td>{{ $item->bank_name }}</td><td>{{ $item->account_name }}</td><td>{{ $item->account_number }}</td><td>{{ number_format($item->opening_balance, 2) }}</td><td>{{ number_format($item->live_balance, 2) }}</td></tr>@endforeach
</tbody></table>
@endif
@if(request()->routeIs('accounting.bank-balances'))
<table data-export-table data-export-title="{{ $accountName }} — Period Summary"><thead><tr><th>Account</th><th>Balance Brought Forward</th><th>Money In</th><th>Money Out</th><th>Balance Carried Forward</th></tr></thead><tbody><tr><td>{{ $accountName }}</td><td>{{ number_format($totals['opening'], 2) }}</td><td>{{ number_format($totals['in'], 2) }}</td><td>{{ number_format($totals['out'], 2) }}</td><td>{{ number_format($totals['closing'], 2) }}</td></tr></tbody></table>
@endif
@if(request()->routeIs('accounting.balance-sheet'))
<table data-export-table data-export-title="Balance Sheet"><thead><tr><th>Type</th><th>Code</th><th>Account</th><th>Balance</th></tr></thead><tbody>
@foreach(['assets','liabilities','equity'] as $type)@foreach($balanceSheet['rows'][$type] as $item)<tr><td>{{ ucfirst($type) }}</td><td>{{ $item->code }}</td><td>{{ $item->name }}</td><td>{{ number_format($item->current_balance, 2) }}</td></tr>@endforeach
<tr><td>Total {{ ucfirst($type) }}</td><td></td><td></td><td>{{ number_format($balanceSheet['totals'][$type], 2) }}</td></tr>@endforeach
<tr><td>Liabilities and Equity</td><td></td><td></td><td>{{ number_format($balanceSheet['totals']['liabilities_equity'], 2) }}</td></tr>
<tr><td>Difference</td><td></td><td></td><td>{{ number_format($balanceSheet['totals']['difference'], 2) }}</td></tr>
</tbody></table>
@endif
@if(request()->routeIs('accounting.t-accounts'))
@foreach($tAccounts as $row)
<table data-export-table data-export-title="{{ $row->account->code }} {{ $row->account->name }}"><thead><tr><th>Date</th><th>Reference / Description</th><th>Debit</th><th>Credit</th></tr></thead><tbody>
@foreach($row->debits as $entry)<tr><td>{{ $entry->transaction_date->toDateString() }}</td><td>{{ $entry->t_account_title ?? $entry->reference_no ?? $entry->description }}</td><td>{{ number_format($entry->amount, 2) }}</td><td></td></tr>@endforeach
@foreach($row->credits as $entry)<tr><td>{{ $entry->transaction_date->toDateString() }}</td><td>{{ $entry->t_account_title ?? $entry->reference_no ?? $entry->description }}</td><td></td><td>{{ number_format($entry->amount, 2) }}</td></tr>@endforeach
<tr><td>Totals</td><td></td><td>{{ number_format($row->debit_total, 2) }}</td><td>{{ number_format($row->credit_total, 2) }}</td></tr>
<tr><td>Closing Balance</td><td>{{ $row->balance_side ?? 'Dr' }}</td><td>{{ ($row->balance_side ?? 'Dr') === 'Dr' ? number_format(abs($row->balance), 2) : '' }}</td><td>{{ ($row->balance_side ?? 'Dr') === 'Cr' ? number_format(abs($row->balance), 2) : '' }}</td></tr>
</tbody></table>
@endforeach
@if($tAccounts->isEmpty())<table data-export-table data-export-title="T Accounts"><thead><tr><th>Account</th><th>Debit</th><th>Credit</th></tr></thead><tbody></tbody></table>@endif
@endif
@endif

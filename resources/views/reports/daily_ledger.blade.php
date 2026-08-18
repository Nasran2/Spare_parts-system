@extends('layouts.app')

@section('title', 'Daily Ledger')
@section('page-title', 'Daily Ledger')

@section('content')
@php
    $maskMoney = function ($value) {
        if (\App\Services\PrivacyModeService::isActiveForUser(auth()->user()) && \App\Services\PrivacyModeService::shouldMaskForCurrentPage()) {
            return \App\Services\PrivacyModeService::maskAmount((float) $value);
        }
        return number_format((float) $value, 2, '.', ',');
    };
@endphp
<div class="space-y-6">
    <form method="get" class="bg-white p-4 rounded shadow flex flex-wrap gap-4 items-end">
        <div>
            <label class="text-sm font-medium text-gray-600">From</label>
            <input type="date" name="from" value="{{ request('from', $from) }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">To</label>
            <input type="date" name="to" value="{{ request('to', $to) }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
        </div>
        @include('partials.quick-date-filter', [
            'fromName' => 'from',
            'toName' => 'to',
            'labelClass' => 'text-sm font-medium text-gray-600',
            'selectClass' => 'mt-1 border rounded px-3 py-2 text-sm w-48',
        ])
        <div class="flex items-center gap-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Filter</button>
            <a href="{{ route('reports.daily-ledger') }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
            <a href="{{ route('reports.daily-ledger.csv', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-emerald-600 text-white rounded text-sm"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ route('reports.daily-ledger.pdf', request()->all()) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </form>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded shadow border-l-4 border-blue-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Main Account (Cash)</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $maskMoney($mainAccountBalance) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow border-l-4 border-orange-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Petty Cash Balance</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $maskMoney($pettyCashBalance) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow border-l-4 border-emerald-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sales Revenue</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $maskMoney($salesRevenue) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow border-l-4 border-red-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Period Expenses (Out)</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ $maskMoney($totalExpensePeriod) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
        <div class="bg-white p-4 rounded shadow border-l-4 border-indigo-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Sales</p>
            <p class="text-xl font-bold text-gray-800 mt-1">{{ $maskMoney($totalSales) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow border-l-4 border-green-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Cash Received</p>
            <p class="text-xl font-bold text-gray-800 mt-1">{{ $maskMoney($totalCashReceived) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow border-l-4 border-purple-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Cheque Received</p>
            <p class="text-xl font-bold text-gray-800 mt-1">{{ $maskMoney($totalChequeReceived) }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow border-l-4 border-rose-500">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Due Amount</p>
            <p class="text-xl font-bold text-gray-800 mt-1">{{ $maskMoney($totalDueAmount) }}</p>
        </div>
    </div>

    @php
        $timezone = config('app.timezone', 'Asia/Colombo');
        
        $renderTable = function($transactions, $title, $iconClass, $openingBalance, $closingBalance) use ($maskMoney, $from, $to) {
            $html = '<div class="bg-white rounded shadow p-4 mt-6">';
            $html .= '<h4 class="font-semibold mb-4 text-gray-800 text-lg border-b pb-2"><i class="' . $iconClass . ' mr-2"></i>' . $title . '</h4>';
            $html .= '<div class="overflow-x-auto"><table class="min-w-full text-sm">';
            $html .= '<thead class="bg-gray-50 border-b"><tr class="text-left text-gray-600">';
            $html .= '<th class="px-4 py-3 font-medium">Date</th>';
            $html .= '<th class="px-4 py-3 font-medium">Type</th>';
            $html .= '<th class="px-4 py-3 font-medium">Account</th>';
            $html .= '<th class="px-4 py-3 font-medium">Related Account</th>';
            $html .= '<th class="px-4 py-3 font-medium">Reference</th>';
            $html .= '<th class="px-4 py-3 font-medium">Description & Details</th>';
            $html .= '<th class="px-4 py-3 font-medium text-right text-emerald-700">Money In (Dr)</th>';
            $html .= '<th class="px-4 py-3 font-medium text-right text-red-700">Money Out (Cr)</th>';
            $html .= '<th class="px-4 py-3 font-medium text-right text-blue-700">Balance</th>';
            $html .= '</tr></thead><tbody class="divide-y">';
            
            // Opening Balance Row
            $html .= '<tr class="bg-blue-50/50">';
            $html .= '<td class="px-4 py-3 text-gray-700 whitespace-nowrap">' . ($from ?? '') . '</td>';
            $html .= '<td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-700 font-medium">Balance</span></td>';
            $html .= '<td class="px-4 py-3 text-gray-500">—</td>';
            $html .= '<td class="px-4 py-3 text-gray-500">—</td>';
            $html .= '<td class="px-4 py-3 text-gray-500">—</td>';
            $html .= '<td class="px-4 py-3 text-gray-800 font-medium italic">Opening Balance</td>';
            $html .= '<td class="px-4 py-3 text-center text-gray-400">—</td>';
            $html .= '<td class="px-4 py-3 text-center text-gray-400">—</td>';
            $html .= '<td class="px-4 py-3 text-right text-blue-700 font-bold whitespace-nowrap">' . $maskMoney($openingBalance) . '</td>';
            $html .= '</tr>';

            if ($transactions->isEmpty()) {
                $html .= '<tr><td colspan="9" class="px-4 py-8 text-center text-gray-500 bg-gray-50"><i class="fas fa-folder-open text-3xl mb-3 text-gray-300 block"></i>No transactions found.</td></tr>';
            } else {
                foreach ($transactions as $t) {
                    $date = $t->transaction_date ? $t->transaction_date->format('Y-m-d') : '';
                    $type = ucfirst(str_replace('_', ' ', $t->source_type));
                    $accName = $t->account?->name ?? '—';
                    $relAccName = $t->relatedAccount?->name ?? '—';
                    $refNo = $t->reference_no ?? '—';
                    $moneyIn = $t->direction === 'in' ? $maskMoney($t->amount) : '—';
                    $moneyOut = $t->direction === 'out' ? $maskMoney($t->amount) : '—';
                    $balance = $maskMoney($t->running_balance);
                    
                    $desc = htmlspecialchars($t->description ?? '—');
                    
                    if ($t->source_type === 'payment' && $t->cheque_details && $t->cheque_details->count() > 0) {
                        $desc .= '<div class="mt-2 space-y-1">';
                        foreach ($t->cheque_details as $cheque) {
                            $desc .= '<div class="text-xs bg-purple-50 text-purple-700 border border-purple-100 p-1 rounded">';
                            $desc .= '<i class="fas fa-money-check mr-1"></i> <strong>' . htmlspecialchars($cheque->bank_name) . '</strong> - ' . htmlspecialchars($cheque->cheque_number) . ' - ' . $maskMoney($cheque->amount);
                            $desc .= '</div>';
                        }
                        $desc .= '</div>';
                    }
                    
                    $html .= '<tr class="hover:bg-gray-50 transition-colors">';
                    $html .= '<td class="px-4 py-3 text-gray-700 whitespace-nowrap">' . $date . '</td>';
                    $html .= '<td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-700 border">' . $type . '</span></td>';
                    $html .= '<td class="px-4 py-3 text-gray-700 font-medium">' . $accName . '</td>';
                    $html .= '<td class="px-4 py-3 text-gray-600">' . $relAccName . '</td>';
                    $html .= '<td class="px-4 py-3 text-gray-600">' . $refNo . '</td>';
                    $html .= '<td class="px-4 py-3 text-gray-600">' . $desc . '</td>';
                    $html .= '<td class="px-4 py-3 text-right text-emerald-600 font-medium whitespace-nowrap">' . $moneyIn . '</td>';
                    $html .= '<td class="px-4 py-3 text-right text-red-600 font-medium whitespace-nowrap">' . $moneyOut . '</td>';
                    $html .= '<td class="px-4 py-3 text-right text-blue-600 font-bold whitespace-nowrap">' . $balance . '</td>';
                    $html .= '</tr>';
                }
            }
            
            // Closing Balance Row
            $html .= '<tr class="bg-gray-100/50 border-t-2 border-gray-200">';
            $html .= '<td class="px-4 py-3 text-gray-700 whitespace-nowrap">' . ($to ?? '') . '</td>';
            $html .= '<td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-1 rounded text-xs bg-gray-200 text-gray-800 font-medium">Balance</span></td>';
            $html .= '<td class="px-4 py-3 text-gray-500">—</td>';
            $html .= '<td class="px-4 py-3 text-gray-500">—</td>';
            $html .= '<td class="px-4 py-3 text-gray-500">—</td>';
            $html .= '<td class="px-4 py-3 text-gray-800 font-medium italic">Closing Balance</td>';
            $html .= '<td class="px-4 py-3 text-center text-gray-400">—</td>';
            $html .= '<td class="px-4 py-3 text-center text-gray-400">—</td>';
            $html .= '<td class="px-4 py-3 text-right text-blue-700 font-bold whitespace-nowrap text-lg">' . $maskMoney($closingBalance) . '</td>';
            $html .= '</tr>';

            $html .= '</tbody></table></div></div>';
            return $html;
        };
    @endphp

    {!! $renderTable($mainTransactions, 'Main Account (Cash) Transactions', 'fas fa-wallet text-blue-600', $mainOpeningBalance, $mainClosingBalance) !!}
    {!! $renderTable($chequeTransactions, 'Customer Receivable (Cheques)', 'fas fa-money-check text-purple-600', $chequeOpeningBalance, $chequeClosingBalance) !!}
    {!! $renderTable($pettyTransactions, 'Petty Cash Transactions', 'fas fa-cash-register text-orange-600', $pettyOpeningBalance, $pettyClosingBalance) !!}

</div>
@endsection

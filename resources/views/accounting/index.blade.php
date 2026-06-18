@extends('layouts.app')

@section('title', 'Accounting')
@section('page-title', 'Accounting Management')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-5">
            <p class="text-sm text-gray-500">Assets</p>
            <p class="text-2xl font-bold text-blue-700">{{ number_format($totals['assets'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <p class="text-sm text-gray-500">Liabilities</p>
            <p class="text-2xl font-bold text-red-700">{{ number_format($totals['liabilities'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <p class="text-sm text-gray-500">Income</p>
            <p class="text-2xl font-bold text-green-700">{{ number_format($totals['income'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <p class="text-sm text-gray-500">Expenses</p>
            <p class="text-2xl font-bold text-orange-700">{{ number_format($totals['expenses'], 2) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-lg shadow overflow-hidden">
            <div class="p-5 border-b flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-800">QuickBooks Chart of Accounts</h3>
                    <p class="text-sm text-gray-500">Cash, credit, bank, liabilities, income and expense accounts</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Account</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-right">Opening</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($accounts as $account)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $account->code }}</td>
                                <td class="px-4 py-3">{{ $account->name }}</td>
                                <td class="px-4 py-3"><span class="px-2 py-1 bg-gray-100 rounded text-xs">{{ ucfirst($account->type) }} / {{ $account->subtype ?: 'General' }}</span></td>
                                <td class="px-4 py-3 text-right">{{ number_format($account->opening_balance, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($account->current_balance, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Add Account</h3>
            <form method="POST" action="{{ route('accounting.accounts.store') }}" class="space-y-3">
                @csrf
                <input name="code" placeholder="Code e.g. 610-010" class="w-full border rounded-lg px-3 py-2" required>
                <input name="name" placeholder="Account name" class="w-full border rounded-lg px-3 py-2" required>
                <select name="type" class="w-full border rounded-lg px-3 py-2" required>
                    <option value="asset">Asset</option>
                    <option value="liability">Liability</option>
                    <option value="equity">Equity</option>
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                </select>
                <input name="subtype" placeholder="Subtype" class="w-full border rounded-lg px-3 py-2">
                <input name="opening_balance" type="number" step="0.01" value="0" class="w-full border rounded-lg px-3 py-2">
                <button class="w-full bg-blue-600 text-white rounded-lg px-4 py-2">Save Account</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Cash / Credit / Cheque / Bank Transaction</h3>
            <form method="POST" action="{{ route('accounting.transactions.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @csrf
                <select name="account_id" class="border rounded-lg px-3 py-2" required>
                    <option value="">Main account</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                    @endforeach
                </select>
                <select name="related_account_id" class="border rounded-lg px-3 py-2">
                    <option value="">Related account</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" class="border rounded-lg px-3 py-2" required>
                <select name="direction" class="border rounded-lg px-3 py-2" required>
                    <option value="in">Money In</option>
                    <option value="out">Money Out</option>
                </select>
                <select name="payment_method" class="border rounded-lg px-3 py-2" required>
                    <option value="cash">Cash</option>
                    <option value="credit">Credit</option>
                    <option value="cheque">Cheque</option>
                    <option value="bank_deposit">Bank Deposit</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="card">Card</option>
                    <option value="mobile_payment">Mobile Payment</option>
                </select>
                <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" class="border rounded-lg px-3 py-2" required>
                <input name="cheque_number" placeholder="Cheque number" class="border rounded-lg px-3 py-2">
                <input name="reference_no" placeholder="Reference no" class="border rounded-lg px-3 py-2">
                <input name="description" placeholder="Description" class="md:col-span-2 border rounded-lg px-3 py-2">
                <button class="md:col-span-2 bg-green-600 text-white rounded-lg px-4 py-2">Record Transaction</button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-5 border-b">
                <h3 class="font-semibold text-gray-800">Recent Ledger</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Account</th>
                            <th class="px-4 py-3 text-left">Method</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($transactions as $transaction)
                            <tr>
                                <td class="px-4 py-3">{{ $transaction->transaction_date->format('M d, Y') }}</td>
                                <td class="px-4 py-3">{{ $transaction->account->name }}</td>
                                <td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($transaction->payment_method)) }} {{ $transaction->cheque_number ? '(' . $transaction->cheque_number . ')' : '' }}</td>
                                <td class="px-4 py-3 text-right {{ $transaction->direction === 'in' ? 'text-green-700' : 'text-red-700' }}">{{ $transaction->direction === 'in' ? '+' : '-' }}{{ number_format($transaction->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No ledger entries yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Bank Accounts & Reconciliation</h3>
            <form method="POST" action="{{ route('accounting.banks.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">
                @csrf
                <input name="bank_name" placeholder="Bank" class="border rounded-lg px-3 py-2" required>
                <input name="account_name" placeholder="Account name" class="border rounded-lg px-3 py-2" required>
                <input name="account_number" placeholder="Account no" class="border rounded-lg px-3 py-2">
                <input type="number" step="0.01" name="opening_balance" placeholder="Opening" class="border rounded-lg px-3 py-2">
                <button class="md:col-span-4 bg-blue-600 text-white rounded-lg px-4 py-2">Add Bank Account</button>
            </form>
            @foreach($banks as $bank)
                <form method="POST" action="{{ route('accounting.banks.reconcile', $bank) }}" class="border rounded-lg p-4 mb-3">
                    @csrf
                    <div class="flex justify-between gap-3 mb-3">
                        <div>
                            <p class="font-semibold">{{ $bank->bank_name }} - {{ $bank->account_name }}</p>
                            <p class="text-sm text-gray-500">System: {{ number_format($bank->chartAccount->current_balance, 2) }} | Last statement: {{ number_format($bank->statement_balance, 2) }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <input type="date" name="statement_date" value="{{ date('Y-m-d') }}" class="border rounded-lg px-3 py-2" required>
                        <input type="number" step="0.01" name="statement_balance" placeholder="Bank statement balance" class="border rounded-lg px-3 py-2" required>
                        <input name="notes" placeholder="Notes" class="border rounded-lg px-3 py-2">
                    </div>
                    <button class="mt-3 bg-gray-800 text-white rounded-lg px-4 py-2">Mark Reconciliation</button>
                </form>
            @endforeach
        </div>

        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Petty Cash</h3>
            <form method="POST" action="{{ route('accounting.petty-cash.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
                @csrf
                <input name="name" placeholder="Fund name" class="border rounded-lg px-3 py-2" required>
                <input type="number" step="0.01" name="opening_balance" placeholder="Opening balance" class="border rounded-lg px-3 py-2" required>
                <button class="bg-blue-600 text-white rounded-lg px-4 py-2">Create Fund</button>
            </form>
            <form method="POST" action="{{ route('accounting.petty-cash.expenses.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @csrf
                <select name="petty_cash_fund_id" class="border rounded-lg px-3 py-2" required>
                    <option value="">Petty cash fund</option>
                    @foreach($pettyFunds as $fund)
                        <option value="{{ $fund->id }}">{{ $fund->name }} - {{ number_format($fund->current_balance, 2) }}</option>
                    @endforeach
                </select>
                <select name="expense_category_id" class="border rounded-lg px-3 py-2" required>
                    <option value="">Expense category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="expense_date" value="{{ date('Y-m-d') }}" class="border rounded-lg px-3 py-2" required>
                <input type="number" step="0.01" name="amount" placeholder="Amount" class="border rounded-lg px-3 py-2" required>
                <input name="voucher_no" placeholder="Voucher no" class="border rounded-lg px-3 py-2">
                <input name="description" placeholder="Description" class="border rounded-lg px-3 py-2">
                <button class="md:col-span-2 bg-orange-600 text-white rounded-lg px-4 py-2">Record Petty Cash Expense</button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-5 border-b">
            <h3 class="font-semibold text-gray-800">Reconciliation History</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Bank</th>
                    <th class="px-4 py-3 text-right">Statement</th>
                    <th class="px-4 py-3 text-right">System</th>
                    <th class="px-4 py-3 text-right">Difference</th>
                    <th class="px-4 py-3 text-left">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($reconciliations as $rec)
                    <tr>
                        <td class="px-4 py-3">{{ $rec->statement_date->format('M d, Y') }}</td>
                        <td class="px-4 py-3">{{ $rec->bankAccount->bank_name }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($rec->statement_balance, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($rec->system_balance, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($rec->difference, 2) }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs {{ $rec->status === 'tallied' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">{{ ucfirst($rec->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No reconciliations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

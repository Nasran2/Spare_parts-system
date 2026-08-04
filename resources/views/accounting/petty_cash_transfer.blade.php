@extends('layouts.app')

@section('title', 'Petty Cash Transfer')
@section('page-title', 'Petty Cash Transfer')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-md p-6 border-t-4 border-blue-500">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Main Account (Cash)</h3>
                <i class="fas fa-wallet text-2xl text-blue-500"></i>
            </div>
            <p class="text-sm text-gray-500 uppercase tracking-wide">Current Balance</p>
            <p class="text-3xl font-bold text-blue-700 mt-1">Rs. {{ number_format($mainAccount->current_balance, 2) }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 border-t-4 border-green-500">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Petty Cash (Total)</h3>
                <i class="fas fa-money-bill-wave text-2xl text-green-500"></i>
            </div>
            <p class="text-sm text-gray-500 uppercase tracking-wide">Combined Balance</p>
            <p class="text-3xl font-bold text-green-700 mt-1">Rs. {{ number_format($pettyFunds->sum('current_balance'), 2) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2"><i class="fas fa-exchange-alt mr-2 text-gray-500"></i> Make a Transfer</h3>
        
        <form action="{{ route('accounting.petty-cash-transfer.store') }}" method="POST" class="space-y-5">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Transfer Direction *</label>
                    <select name="direction" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 bg-gray-50" required>
                        <option value="to_petty_cash" {{ old('direction') == 'to_petty_cash' ? 'selected' : '' }}>Main Account ➔ Petty Cash</option>
                        <option value="to_main_account" {{ old('direction') == 'to_main_account' ? 'selected' : '' }}>Petty Cash ➔ Main Account</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Target/Source Petty Cash Fund *</label>
                    <select name="petty_cash_fund_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 bg-gray-50" required>
                        <option value="">Select fund...</option>
                        @foreach($pettyFunds as $fund)
                            <option value="{{ $fund->id }}" {{ old('petty_cash_fund_id') == $fund->id ? 'selected' : '' }}>
                                {{ $fund->name }} (Bal: {{ number_format($fund->current_balance, 2) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Transfer Date *</label>
                    <input type="date" name="transfer_date" value="{{ old('transfer_date', date('Y-m-d')) }}" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Amount (Rs.) *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-lg" placeholder="0.00" required>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <input type="text" name="description" value="{{ old('description') }}" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Optional notes...">
                </div>
            </div>
            
            <div class="pt-4 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 shadow-md flex items-center transition">
                    <i class="fas fa-check-circle mr-2"></i> Confirm Transfer
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

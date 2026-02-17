@extends('layouts.app')

@section('title', 'POS Settings')
@section('page-title', 'POS Settings')

@section('content')
<div class="space-y-6">

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        <!-- Settings Navigation -->
        @include('settings.partials.sidebar')

        <!-- Settings Content -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-cash-register text-blue-600 mr-2"></i>POS Settings
                </h3>

                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.save') }}">
                    @csrf

                    <div class="space-y-8">

                        <!-- Layout -->
                        <div class="border-b pb-6">
                            <h4 class="font-bold text-gray-700 mb-4">
                                <i class="fas fa-palette text-indigo-600 mr-2"></i>POS Screen Layout
                            </h4>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Layout</label>
                                    <select name="pos_layout" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="default" {{ old('pos_layout', $settings['pos_layout']) === 'default' ? 'selected' : '' }}>Default (Old)</option>
                                        <option value="modern" {{ old('pos_layout', $settings['pos_layout']) === 'modern' ? 'selected' : '' }}>Modern (New Fullscreen)</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Switch between the old POS screen and the new fullscreen POS screen.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Card fee -->
                        <div>
                            <h4 class="font-bold text-gray-700 mb-4">
                                <i class="fas fa-credit-card text-emerald-600 mr-2"></i>Card Sale Fee
                            </h4>

                            <input type="hidden" name="pos_card_fee_enabled" value="0">
                            <input type="hidden" name="pos_card_fee_record_expense" value="0">

                            <div class="space-y-4">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <label class="font-semibold text-gray-700">Enable card fee</label>
                                        <p class="text-sm text-gray-500">Apply a percentage fee for card payments.</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="pos_card_fee_enabled" value="1" {{ old('pos_card_fee_enabled', $settings['pos_card_fee_enabled']) ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Fee Rate (%)</label>
                                        <input
                                            type="number"
                                            name="pos_card_fee_rate"
                                            step="0.01" min="0" max="100"
                                            value="{{ old('pos_card_fee_rate', $settings['pos_card_fee_rate']) }}"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('pos_card_fee_rate') border-red-500 @enderror"
                                        >
                                        @error('pos_card_fee_rate')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Who pays the fee?</label>
                                        <select name="pos_card_fee_mode" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                            <option value="customer" {{ old('pos_card_fee_mode', $settings['pos_card_fee_mode']) === 'customer' ? 'selected' : '' }}>Customer pays (add to bill)</option>
                                            <option value="seller" {{ old('pos_card_fee_mode', $settings['pos_card_fee_mode']) === 'seller' ? 'selected' : '' }}>Seller pays (record as expense)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Expense Category (seller mode)</label>
                                        <select name="pos_card_fee_expense_category_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                            <option value="0">-- None --</option>
                                            @foreach($expenseCategories as $cat)
                                                <option value="{{ $cat->id }}" {{ (int)old('pos_card_fee_expense_category_id', $settings['pos_card_fee_expense_category_id']) === (int)$cat->id ? 'selected' : '' }}>
                                                    {{ $cat->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Used only when Seller pays is selected.</p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <label class="font-semibold text-gray-700">Record seller card fee as expense</label>
                                        <p class="text-sm text-gray-500">Automatically create an Expense record when payment method is Card and seller pays the fee.</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="pos_card_fee_record_expense" value="1" {{ old('pos_card_fee_record_expense', $settings['pos_card_fee_record_expense']) ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>

                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div class="flex items-start">
                                        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                                        <div>
                                            <p class="text-sm text-blue-800 font-semibold">How it works</p>
                                            <ul class="text-xs text-blue-700 mt-1 list-disc pl-5 space-y-1">
                                                <li><b>Customer pays</b>: fee is added to Total Payable for card payments.</li>
                                                <li><b>Seller pays</b>: customer total stays unchanged, and the fee can be recorded as an Expense (optional).</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg font-semibold">
                                <i class="fas fa-save mr-2"></i>Save POS Settings
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

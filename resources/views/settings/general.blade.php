@extends('layouts.app')

@section('title', 'General Settings')
@section('page-title', 'General Settings')

@section('content')
<div class="space-y-6">
    
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Settings Navigation -->
        @include('settings.partials.sidebar')

        <!-- Settings Content -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-cog text-purple-600 mr-2"></i>General Settings
                </h3>

                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.save') }}">
                    @csrf
                    <div class="space-y-6">
                        
                        <!-- Currency Settings -->
                        <div class="border-b pb-4">
                            <h4 class="font-bold text-gray-700 mb-4">
                                <i class="fas fa-dollar-sign text-green-600 mr-2"></i>Currency Settings
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Currency Symbol</label>
                                    <input 
                                        type="text"
                                        name="currency"
                                        value="{{ old('currency', $settings['currency']) }}"
                                        placeholder="Rs"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Currency Position</label>
                                    <select 
                                        name="currency_position"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="before" {{ old('currency_position', $settings['currency_position']) == 'before' ? 'selected' : '' }}>Before Amount ({{ $currency }} 100)</option>
                                        <option value="after" {{ old('currency_position', $settings['currency_position']) == 'after' ? 'selected' : '' }}>After Amount (100 Rs)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Decimal Places</label>
                                    <input 
                                        type="number"
                                        name="decimal_places"
                                        min="0" max="4"
                                        value="{{ old('decimal_places', $settings['decimal_places']) }}"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Date & Time Settings -->
                        <div class="border-b pb-4">
                            <h4 class="font-bold text-gray-700 mb-4">
                                <i class="fas fa-calendar text-blue-600 mr-2"></i>Date & Time Settings
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date Format</label>
                                    <select 
                                        name="date_format"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="Y-m-d" {{ old('date_format', $settings['date_format']) == 'Y-m-d' ? 'selected' : '' }}>YYYY-MM-DD (2025-11-24)</option>
                                        <option value="d/m/Y" {{ old('date_format', $settings['date_format']) == 'd/m/Y' ? 'selected' : '' }}>DD/MM/YYYY (24/11/2025)</option>
                                        <option value="m/d/Y" {{ old('date_format', $settings['date_format']) == 'm/d/Y' ? 'selected' : '' }}>MM/DD/YYYY (11/24/2025)</option>
                                        <option value="d-M-Y" {{ old('date_format', $settings['date_format']) == 'd-M-Y' ? 'selected' : '' }}>DD-Mon-YYYY (24-Nov-2025)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Time Format</label>
                                    <select 
                                        name="time_format"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="H:i:s" {{ old('time_format', $settings['time_format']) == 'H:i:s' ? 'selected' : '' }}>24 Hour (14:30:00)</option>
                                        <option value="h:i A" {{ old('time_format', $settings['time_format']) == 'h:i A' ? 'selected' : '' }}>12 Hour (02:30 PM)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Timezone</label>
                                    <select 
                                        name="timezone"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="UTC" {{ old('timezone', $settings['timezone']) == 'UTC' ? 'selected' : '' }}>UTC</option>
                                        <option value="Asia/Colombo" {{ old('timezone', $settings['timezone']) == 'Asia/Colombo' ? 'selected' : '' }}>Asia/Colombo (Sri Lanka)</option>
                                        <option value="Asia/Kolkata" {{ old('timezone', $settings['timezone']) == 'Asia/Kolkata' ? 'selected' : '' }}>Asia/Kolkata (IST)</option>
                                        <option value="America/New_York" {{ old('timezone', $settings['timezone']) == 'America/New_York' ? 'selected' : '' }}>America/New_York (EST)</option>
                                        <option value="Europe/London" {{ old('timezone', $settings['timezone']) == 'Europe/London' ? 'selected' : '' }}>Europe/London (GMT)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Display Settings -->
                        <div class="border-b pb-4">
                            <h4 class="font-bold text-gray-700 mb-4">
                                <i class="fas fa-desktop text-indigo-600 mr-2"></i>Display Settings
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Language</label>
                                    <select 
                                        name="language"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="en" {{ old('language', $settings['language']) == 'en' ? 'selected' : '' }}>English</option>
                                        <option value="es" {{ old('language', $settings['language']) == 'es' ? 'selected' : '' }}>Español</option>
                                        <option value="fr" {{ old('language', $settings['language']) == 'fr' ? 'selected' : '' }}>Français</option>
                                        <option value="hi" {{ old('language', $settings['language']) == 'hi' ? 'selected' : '' }}>हिन्दी</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Items Per Page</label>
                                    <input 
                                        type="number"
                                        name="items_per_page"
                                        min="5" max="100"
                                        value="{{ old('items_per_page', $settings['items_per_page']) }}"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                    <p class="text-xs text-gray-500 mt-1">Number of items to display in lists</p>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Management -->
                        <div>
                            <h4 class="font-bold text-gray-700 mb-4">
                                <i class="fas fa-boxes text-amber-600 mr-2"></i>Stock Management
                            </h4>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <label class="font-semibold text-gray-700">Low Stock Warning</label>
                                    <p class="text-sm text-gray-500">Show warnings when products are running low</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="low_stock_warning" value="1" {{ old('low_stock_warning', $settings['low_stock_warning']) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                        </div>

                        <!-- VAT Settings -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="font-bold text-gray-700 mb-4">
                                <i class="fas fa-receipt text-green-600 mr-2"></i>VAT Settings
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Enable VAT</label>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="vat_enabled" value="1" {{ old('vat_enabled', $settings['vat_enabled']) ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">VAT Rate (%)</label>
                                    <input 
                                        type="number"
                                        name="vat_rate"
                                        step="0.01" min="0" max="100"
                                        value="{{ old('vat_rate', $settings['vat_rate']) }}"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                        placeholder="e.g., 15"
                                    >
                                    <p class="text-xs text-gray-500 mt-1">This percentage will be used across the system. If VAT is disabled, no VAT will be applied.</p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-200 mt-6">
                            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg">
                                <i class="fas fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>

</div>
@endsection

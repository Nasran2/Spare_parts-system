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
                        <div id="vat-settings" class="border-t border-gray-200 pt-6 scroll-mt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-bold text-gray-700">
                                    <i class="fas fa-receipt text-green-600 mr-2"></i>Sri Lanka VAT Settings
                                </h4>
                                <span class="text-xs font-semibold px-2 py-1 rounded-full bg-green-50 text-green-700">
                                    Template {{ $settings['tax_template_version'] }}
                                </span>
                            </div>

                            @if($errors->any())
                                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                @foreach([
                                    ['vat_enabled', 'Enable VAT', 'VAT is applied only to new transactions.'],
                                    ['vat_registered', 'Business VAT Registered', 'Required before official Tax Invoices can be issued.'],
                                    ['allow_product_override', 'Allow Product-Level VAT Override', 'Products may use their own status, rate and price mode.'],
                                    ['regular_invoice_vat_note', 'Show VAT-Inclusive Note', 'Adds “Prices are inclusive of applicable VAT” to regular receipts.'],
                                ] as [$name, $label, $help])
                                    <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4">
                                        <div class="pr-4">
                                            <div class="font-semibold text-gray-700">{{ $label }}</div>
                                            <p class="text-xs text-gray-500 mt-1">{{ $help }}</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                            <input type="checkbox" name="{{ $name }}" value="1" {{ old($name, $settings[$name]) ? 'checked' : '' }} class="sr-only peer">
                                            <span class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></span>
                                        </label>
                                    </div>
                                @endforeach

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier TIN</label>
                                    <input type="text" name="supplier_tin" inputmode="numeric" pattern="[0-9]{9,12}" maxlength="12"
                                           value="{{ old('supplier_tin', $settings['supplier_tin']) }}"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                           placeholder="9 to 12 digits">
                                    <p class="text-xs text-gray-500 mt-1">Required when the business is VAT registered. Spaces are not accepted.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Default VAT Rate (%)</label>
                                    <input type="number" name="default_vat_rate" step="0.0001" min="0" max="100"
                                           value="{{ old('default_vat_rate', $settings['default_vat_rate']) }}"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Default Selling Price VAT Mode</label>
                                    <select name="default_sale_price_mode" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                        <option value="inclusive" @selected(old('default_sale_price_mode', $settings['default_sale_price_mode']) === 'inclusive')>Hide VAT</option>
                                        <option value="exclusive" @selected(old('default_sale_price_mode', $settings['default_sale_price_mode']) === 'exclusive')>Show VAT</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Hide VAT means VAT is already included in the selling price. The customer pays the displayed price without VAT being added again.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Default Purchase Price VAT Mode</label>
                                    <select name="default_purchase_price_mode" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                        <option value="inclusive" @selected(old('default_purchase_price_mode', $settings['default_purchase_price_mode']) === 'inclusive')>Hide VAT</option>
                                        <option value="exclusive" @selected(old('default_purchase_price_mode', $settings['default_purchase_price_mode']) === 'exclusive')>Show VAT</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Show VAT means VAT is calculated and added on top of the entered purchase price.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Customer Invoice VAT Display</label>
                                    <select name="customer_invoice_vat_display" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                        <option value="hide_inclusive" @selected(old('customer_invoice_vat_display', $settings['customer_invoice_vat_display']) === 'hide_inclusive')>Hide Separate VAT When Price Is Inclusive</option>
                                        <option value="always_show" @selected(old('customer_invoice_vat_display', $settings['customer_invoice_vat_display']) === 'always_show')>Always Show VAT Breakdown</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">VAT Effective Date</label>
                                    <input type="date" name="vat_effective_date"
                                           value="{{ old('vat_effective_date', $settings['vat_effective_date']) }}"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    <p class="text-xs text-gray-500 mt-1">A new version is created; completed transactions retain their stored snapshot.</p>
                                </div>
                            </div>

                            <div class="mt-6 border-t border-gray-200 pt-5">
                                <h5 class="font-semibold text-gray-700 mb-4">Tax Invoice Numbering</h5>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-2">Prefix</label>
                                        <input name="tax_invoice_prefix" value="{{ old('tax_invoice_prefix', $settings['tax_invoice_prefix']) }}" class="w-full px-3 py-2 border rounded-lg" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-2">Starting Number</label>
                                        <input type="number" min="1" name="tax_invoice_starting_number" value="{{ old('tax_invoice_starting_number', $settings['tax_invoice_starting_number']) }}" class="w-full px-3 py-2 border rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-2">Next Number</label>
                                        <input type="number" min="1" name="tax_invoice_next_number" value="{{ old('tax_invoice_next_number', $settings['tax_invoice_next_number']) }}" class="w-full px-3 py-2 border rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-2">Branch / Store Code</label>
                                        <input name="tax_branch_code" value="{{ old('tax_branch_code', $settings['tax_branch_code']) }}" class="w-full px-3 py-2 border rounded-lg" placeholder="Optional">
                                    </div>
                                </div>
                                <input type="hidden" name="tax_template_version" value="sl-vat-2025.1">
                                <p class="text-xs text-gray-500 mt-3">Invoice numbers are issued atomically and protected by a database unique constraint.</p>
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

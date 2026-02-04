@extends('layouts.app')

@section('title', 'Quotation Settings')
@section('page-title', 'Quotation Settings')

@section('content')
<div class="space-y-6">
    
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Settings Navigation -->
        @include('settings.partials.sidebar')

        <!-- Settings Content -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-file-contract text-amber-600 mr-2"></i>Quotation Settings
                </h3>

                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.save') }}">
                    @csrf
                    <div class="space-y-6">
                        
                        <!-- Quotation Format -->
                        <div class="border-b pb-4">
                            <h4 class="font-bold text-gray-700 mb-4">
                                <i class="fas fa-file-alt text-blue-600 mr-2"></i>Quotation Format
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Quotation Prefix</label>
                                    <input 
                                        type="text"
                                        name="quotation_prefix"
                                        value="{{ old('quotation_prefix', $settings['quotation_prefix']) }}"
                                        placeholder="QUO-"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                    <p class="text-xs text-gray-500 mt-1">Example: QUO-0001, QUO-0002</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Validity (Days)</label>
                                    <input 
                                        type="number"
                                        name="quotation_valid_days"
                                        min="1" max="3650"
                                        value="{{ old('quotation_valid_days', $settings['quotation_valid_days']) }}"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                    <p class="text-xs text-gray-500 mt-1">Valid for how many days</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Paper Size</label>
                                    <select 
                                        name="quotation_paper_size"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="a4" {{ old('quotation_paper_size', $settings['quotation_paper_size']) == 'a4' ? 'selected' : '' }}>A4 (210 × 297 mm)</option>
                                        <option value="letter" {{ old('quotation_paper_size', $settings['quotation_paper_size']) == 'letter' ? 'selected' : '' }}>Letter (8.5 × 11 inch)</option>
                                        <option value="80mm" {{ old('quotation_paper_size', $settings['quotation_paper_size']) == '80mm' ? 'selected' : '' }}>80mm Thermal Receipt</option>
                                        <option value="58mm" {{ old('quotation_paper_size', $settings['quotation_paper_size']) == '58mm' ? 'selected' : '' }}>58mm Thermal Receipt</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Select paper size for printing</p>
                                </div>
                            </div>
                        </div>

                        <!-- Display Options -->
                        <div class="border-b pb-4">
                            <h4 class="font-bold text-gray-700 mb-4">
                                <i class="fas fa-eye text-purple-600 mr-2"></i>Display Options
                            </h4>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg mb-4">
                                <div>
                                    <label class="font-semibold text-gray-700">Show Business Logo</label>
                                    <p class="text-sm text-gray-500">Display your business logo on quotations</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="quotation_show_logo" value="1" {{ old('quotation_show_logo', $settings['quotation_show_logo']) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Footer Text</label>
                                <textarea 
                                    rows="2"
                                    name="quotation_footer_text"
                                    placeholder="Thank you for your interest!"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >{{ old('quotation_footer_text', $settings['quotation_footer_text']) }}</textarea>
                                <p class="text-xs text-gray-500 mt-1">Appears at the bottom of quotations</p>
                            </div>
                        </div>

                        <!-- Terms & Conditions -->
                        <div>
                            <h4 class="font-bold text-gray-700 mb-4">
                                <i class="fas fa-file-contract text-amber-600 mr-2"></i>Terms & Conditions
                            </h4>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Quotation Terms</label>
                                <textarea 
                                    rows="5"
                                    name="quotation_terms"
                                    placeholder="Enter your quotation terms and conditions..."
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >{{ old('quotation_terms', $settings['quotation_terms']) }}</textarea>
                                <p class="text-xs text-gray-500 mt-1">Standard terms printed on all quotations</p>
                            </div>
                        </div>

                        <!-- Preview Info -->
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-lightbulb text-amber-600 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-sm text-amber-800 font-semibold">Quotation Tips</p>
                                    <ul class="text-xs text-amber-700 mt-2 space-y-1 list-disc list-inside">
                                        <li>Set validity period based on your pricing stability</li>
                                        <li>Include clear terms to avoid misunderstandings</li>
                                        <li>A4 size recommended for professional presentations</li>
                                        <li>Thermal sizes suitable for quick quotes</li>
                                    </ul>
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

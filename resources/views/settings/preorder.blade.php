@extends('layouts.app')

@section('title', 'Pre-Order Settings')
@section('page-title', 'Pre-Order Settings')

@section('content')
<div class="space-y-6">
    
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Settings Navigation -->
        @include('settings.partials.sidebar')

        <!-- Settings Content -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-box-open text-blue-600 mr-2"></i>Pre-Order Settings
                </h3>

                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.save') }}">
                    @csrf
                    <div class="space-y-6">
                        
                        <!-- PDF Theme Settings -->
                        <div class="border-b pb-4">
                            <h4 class="font-bold text-gray-700 mb-4">
                                <i class="fas fa-palette text-blue-600 mr-2"></i>PDF Appearance
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">PDF Theme Color (Lines)</label>
                                    <input 
                                        type="color"
                                        name="preorder_pdf_line_color"
                                        value="{{ old('preorder_pdf_line_color', $settings['preorder_pdf_line_color']) }}"
                                        class="h-10 w-full px-1 py-1 border border-gray-300 rounded-lg cursor-pointer focus:ring-2 focus:ring-blue-500"
                                    >
                                    <p class="text-xs text-gray-500 mt-1">Color of table lines and borders</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">PDF Text Color</label>
                                    <input 
                                        type="color"
                                        name="preorder_pdf_text_color"
                                        value="{{ old('preorder_pdf_text_color', $settings['preorder_pdf_text_color']) }}"
                                        class="h-10 w-full px-1 py-1 border border-gray-300 rounded-lg cursor-pointer focus:ring-2 focus:ring-blue-500"
                                    >
                                    <p class="text-xs text-gray-500 mt-1">Primary text color</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Table Heading Color</label>
                                    <input 
                                        type="color"
                                        name="preorder_pdf_heading_color"
                                        value="{{ old('preorder_pdf_heading_color', $settings['preorder_pdf_heading_color']) }}"
                                        class="h-10 w-full px-1 py-1 border border-gray-300 rounded-lg cursor-pointer focus:ring-2 focus:ring-blue-500"
                                    >
                                    <p class="text-xs text-gray-500 mt-1">Background color for table headers</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Logo Shape</label>
                                    <select 
                                        name="preorder_pdf_logo_shape"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="original" {{ old('preorder_pdf_logo_shape', $settings['preorder_pdf_logo_shape']) == 'original' ? 'selected' : '' }}>Original (No changes)</option>
                                        <option value="square" {{ old('preorder_pdf_logo_shape', $settings['preorder_pdf_logo_shape']) == 'square' ? 'selected' : '' }}>Square</option>
                                        <option value="round" {{ old('preorder_pdf_logo_shape', $settings['preorder_pdf_logo_shape']) == 'round' ? 'selected' : '' }}>Round</option>
                                        <option value="box" {{ old('preorder_pdf_logo_shape', $settings['preorder_pdf_logo_shape']) == 'box' ? 'selected' : '' }}>Box Shape (Rounded Edges)</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Select the shape of the business logo on the PDF</p>
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

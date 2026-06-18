@extends('layouts.app')

@section('title', 'Barcode Settings')
@section('page-title', 'Barcode Settings')

@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        @include('settings.partials.sidebar')

        <div class="lg:col-span-3">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-barcode text-indigo-600 mr-2"></i>Barcode Label Settings
                </h3>

                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.save') }}" class="space-y-8">
                    @csrf

                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="flex-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Saved Presets</label>
                            <select id="presetSelect" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select a preset...</option>
                                @foreach($presets as $preset)
                                    <option value="{{ $preset['name'] ?? '' }}">{{ $preset['name'] ?? '' }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Selecting a preset will fill the fields below (not saved until you submit).</p>
                        </div>
                        <div class="w-full lg:w-80">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Default Preset</label>
                            <select name="barcode_default_preset" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">None</option>
                                @foreach($presets as $preset)
                                    @php($name = $preset['name'] ?? '')
                                    <option value="{{ $name }}" {{ $defaultPreset === $name ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Used automatically when printing.</p>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-bold text-gray-700 mb-4">
                            <i class="fas fa-ruler-combined text-blue-600 mr-2"></i>Label Dimensions (cm)
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sticker Width *</label>
                                <input type="number" step="0.01" min="0.1" name="barcode_sticker_width" value="{{ old('barcode_sticker_width', $settings['barcode_sticker_width']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sticker Height *</label>
                                <input type="number" step="0.01" min="0.1" name="barcode_sticker_height" value="{{ old('barcode_sticker_height', $settings['barcode_sticker_height']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Paper Width *</label>
                                <input type="number" step="0.01" min="0.1" name="barcode_paper_width" value="{{ old('barcode_paper_width', $settings['barcode_paper_width']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Labels per Row *</label>
                                <input type="number" step="1" min="1" name="barcode_labels_per_row" value="{{ old('barcode_labels_per_row', $settings['barcode_labels_per_row']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Row Gap (Vertical)</label>
                                <input type="number" step="0.01" min="0" name="barcode_row_gap" value="{{ old('barcode_row_gap', $settings['barcode_row_gap']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sheet Alignment</label>
                                <select name="barcode_alignment" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    @php($alignValue = old('barcode_alignment', $settings['barcode_alignment'] ?? 'left'))
                                    <option value="left" {{ $alignValue === 'left' ? 'selected' : '' }}>Left</option>
                                    <option value="center" {{ $alignValue === 'center' ? 'selected' : '' }}>Center</option>
                                    <option value="right" {{ $alignValue === 'right' ? 'selected' : '' }}>Right</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Top Margin</label>
                                <input type="number" step="0.01" min="0" name="barcode_top_margin" value="{{ old('barcode_top_margin', $settings['barcode_top_margin']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Left Margin</label>
                                <input type="number" step="0.01" min="0" name="barcode_left_margin" value="{{ old('barcode_left_margin', $settings['barcode_left_margin']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Col Gap (Horizontal)</label>
                                <input type="number" step="0.01" min="0" name="barcode_col_gap" value="{{ old('barcode_col_gap', $settings['barcode_col_gap']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Pad Top</label>
                                <input type="number" step="0.01" min="0" name="barcode_sticker_top_padding" value="{{ old('barcode_sticker_top_padding', $settings['barcode_sticker_top_padding']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Pad Bottom</label>
                                <input type="number" step="0.01" min="0" name="barcode_sticker_bottom_padding" value="{{ old('barcode_sticker_bottom_padding', $settings['barcode_sticker_bottom_padding']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-bold text-gray-700 mb-4">
                            <i class="fas fa-text-height text-purple-600 mr-2"></i>Content Font Sizes (px)
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Shop Name</label>
                                <input type="number" step="1" min="1" name="barcode_shop_name_size" value="{{ old('barcode_shop_name_size', $settings['barcode_shop_name_size']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Product Name</label>
                                <input type="number" step="1" min="1" name="barcode_product_name_size" value="{{ old('barcode_product_name_size', $settings['barcode_product_name_size']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Price Tag</label>
                                <input type="number" step="1" min="1" name="barcode_price_tag_size" value="{{ old('barcode_price_tag_size', $settings['barcode_price_tag_size']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Secret Code</label>
                                <input type="number" step="1" min="1" name="barcode_secret_code_size" value="{{ old('barcode_secret_code_size', $settings['barcode_secret_code_size']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Barcode Number</label>
                                <input type="number" step="1" min="1" name="barcode_number_size" value="{{ old('barcode_number_size', $settings['barcode_number_size']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-bold text-gray-700 mb-4">
                            <i class="fas fa-barcode text-emerald-600 mr-2"></i>Barcode Layout
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Barcode Height (cm)</label>
                                <input type="number" step="0.01" min="0.1" name="barcode_height" value="{{ old('barcode_height', $settings['barcode_height']) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    @if($canManageSecretCodes ?? false)
                    <div>
                        <h4 class="font-bold text-gray-700 mb-4">
                            <i class="fas fa-tags text-indigo-600 mr-2"></i>Selling Secret Code
                        </h4>
                        @if($sellingZeroFallback ?? false)
                            <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                                Selling zero fallback is enabled: any unmapped letter will be treated as digit 0.
                            </p>
                        @endif
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <label class="font-semibold text-gray-700">Enable Selling Secret Code</label>
                                <p class="text-sm text-gray-500">When enabled, product forms and barcode print can use encoded selling price.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="barcode_enable_selling_secret_code" value="0">
                                <input type="checkbox" name="barcode_enable_selling_secret_code" value="1" {{ old('barcode_enable_selling_secret_code', $settings['barcode_enable_selling_secret_code'] ?? false) ? 'checked' : '' }} class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mt-4">
                            @for($d = 0; $d <= 9; $d++)
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Digit {{ $d }}</label>
                                    <input type="text" name="barcode_selling_code[{{ $d }}]" value="{{ old('barcode_selling_code.'.$d, $settings['barcode_selling_code_map'][(string) $d] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" maxlength="5">
                                </div>
                            @endfor
                        </div>
                    </div>
                    @endif

                    @if($canManageSecretCodes ?? false)
                    <div>
                        <h4 class="font-bold text-gray-700 mb-4">
                            <i class="fas fa-user-secret text-slate-600 mr-2"></i>Cost Secret Code (0-9)
                        </h4>
                        @if($zeroFallback)
                            <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                                Zero fallback is enabled: any unmapped letter will be treated as digit 0.
                            </p>
                        @endif
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg mb-4">
                            <div>
                                <label class="font-semibold text-gray-700">Show Cost Secret Code</label>
                                <p class="text-sm text-gray-500">Enable to print the encoded cost on labels</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="barcode_show_cost_code" value="0">
                                <input type="checkbox" name="barcode_show_cost_code" value="1" {{ old('barcode_show_cost_code', $settings['barcode_show_cost_code']) ? 'checked' : '' }} class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            @for($d = 0; $d <= 9; $d++)
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Digit {{ $d }}</label>
                                    <input type="text" name="barcode_cost_code[{{ $d }}]" value="{{ old('barcode_cost_code.'.$d, $settings['barcode_cost_code_map'][(string) $d] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" maxlength="5">
                                </div>
                            @endfor
                        </div>
                    </div>
                    @endif

                    <div class="border-t pt-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Save as Preset</label>
                                <input type="text" name="barcode_preset_name" placeholder="Preset name (e.g., small correct)" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg">
                                    <i class="fas fa-save mr-2"></i>Save Settings
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Enter a preset name to save current settings. Leave blank to only update current settings.</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const presets = @json($presets ?? []);
const presetSelect = document.getElementById('presetSelect');

function applyPreset(name) {
    const preset = presets.find(p => p.name === name);
    if (!preset || !preset.settings) { return; }

    Object.entries(preset.settings).forEach(([key, value]) => {
        const field = document.querySelector(`[name="${key}"]`);
        if (field) {
            field.value = value;
        }
    });

    if (preset.settings.barcode_show_cost_code !== undefined) {
        const toggle = document.querySelector('input[name="barcode_show_cost_code"][type="checkbox"]');
        if (toggle) {
            toggle.checked = !!preset.settings.barcode_show_cost_code;
        }
    }

    if (preset.settings.barcode_enable_selling_secret_code !== undefined) {
        const toggle = document.querySelector('input[name="barcode_enable_selling_secret_code"][type="checkbox"]');
        if (toggle) {
            toggle.checked = !!preset.settings.barcode_enable_selling_secret_code;
        }
    }

    if (preset.settings.barcode_cost_code_map) {
        Object.entries(preset.settings.barcode_cost_code_map).forEach(([digit, code]) => {
            const input = document.querySelector(`[name="barcode_cost_code[${digit}]"]`);
            if (input) {
                input.value = code;
            }
        });
    }

    if (preset.settings.barcode_selling_code_map) {
        Object.entries(preset.settings.barcode_selling_code_map).forEach(([digit, code]) => {
            const input = document.querySelector(`[name="barcode_selling_code[${digit}]"]`);
            if (input) {
                input.value = code;
            }
        });
    }
}

presetSelect?.addEventListener('change', (event) => {
    applyPreset(event.target.value);
});
</script>
@endsection

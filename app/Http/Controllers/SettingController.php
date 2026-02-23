<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Display the settings page.
     */
    public function index()
    {
        return redirect()->route('settings.business');
    }

    /**
     * Business Information Settings
     */
    public function business()
    {
        $settings = [
            'shop_name' => Setting::get('shop_name', 'Vehicle POS System'),
            'shop_tagline' => Setting::get('shop_tagline', 'Auto Parts System'),
            'shop_address' => Setting::get('shop_address', ''),
            'shop_phone' => Setting::get('shop_phone', ''),
            'shop_email' => Setting::get('shop_email', ''),
            'shop_logo' => Setting::get('shop_logo', ''),
        ];
        
        return view('settings.business', compact('settings'));
    }

    /**
     * General Settings
     */
    public function general()
    {
        $settings = [
            'currency' => Setting::get('currency', 'Rs'),
            'currency_position' => Setting::get('currency_position', 'before'),
            'decimal_places' => (int) Setting::get('decimal_places', 2),
            'date_format' => Setting::get('date_format', 'Y-m-d'),
            'time_format' => Setting::get('time_format', 'H:i:s'),
            'timezone' => Setting::get('timezone', env('APP_TIMEZONE', 'Asia/Colombo')),
            'language' => Setting::get('language', 'en'),
            'items_per_page' => (int) Setting::get('items_per_page', 10),
            'low_stock_warning' => Setting::get('low_stock_warning', true),
            // VAT Settings
            'vat_enabled' => (bool) Setting::get('vat_enabled', false),
            'vat_rate' => (float) Setting::get('vat_rate', 0),
        ];
        
        return view('settings.general', compact('settings'));
    }

    /**
     * Invoice Settings
     */
    public function invoice()
    {
        $settings = [
            'invoice_prefix' => Setting::get('invoice_prefix', 'INV-'),
            'invoice_paper_size' => Setting::get('invoice_paper_size', 'a4'),
            'invoice_show_logo' => Setting::get('invoice_show_logo', true),
            'invoice_footer_text' => Setting::get('invoice_footer_text', 'Thank you for your business!'),
            'invoice_terms' => Setting::get('invoice_terms', ''),
        ];
        
        return view('settings.invoice', compact('settings'));
    }

    /**
     * Quotation Settings
     */
    public function quotation()
    {
        $settings = [
            'quotation_prefix' => Setting::get('quotation_prefix', 'QUO-'),
            'quotation_valid_days' => (int) Setting::get('quotation_valid_days', 30),
            'quotation_paper_size' => Setting::get('quotation_paper_size', 'a4'),
            'quotation_show_logo' => Setting::get('quotation_show_logo', true),
            'quotation_footer_text' => Setting::get('quotation_footer_text', 'Thank you for your interest!'),
            'quotation_terms' => (string) Setting::get('quotation_terms', ''),
        ];
        
        return view('settings.quotation', compact('settings'));
    }

    /**
     * POS Settings
     */
    public function pos()
    {
        $settings = [
            'pos_layout' => Setting::get('pos_layout', 'default'),

            // Card fee settings
            'pos_card_fee_enabled' => (bool) Setting::get('pos_card_fee_enabled', false),
            'pos_card_fee_rate' => (float) Setting::get('pos_card_fee_rate', 0),
            'pos_card_fee_mode' => Setting::get('pos_card_fee_mode', 'customer'),
            'pos_card_fee_record_expense' => (bool) Setting::get('pos_card_fee_record_expense', true),
            'pos_card_fee_expense_category_id' => (int) Setting::get('pos_card_fee_expense_category_id', 0),
        ];

        $expenseCategories = ExpenseCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('settings.pos', compact('settings', 'expenseCategories'));
    }

    /**
     * Barcode Settings
     */
    public function barcode()
    {
        $defaultMap = [
            '0' => 'E',
            '1' => 'M',
            '2' => 'O',
            '3' => 'D',
            '4' => 'T',
            '5' => 'W',
            '6' => 'I',
            '7' => 'N',
            '8' => 'K',
            '9' => 'L',
        ];

        $settings = [
            'barcode_sticker_width' => (float) Setting::get('barcode_sticker_width', 3),
            'barcode_sticker_height' => (float) Setting::get('barcode_sticker_height', 2),
            'barcode_paper_width' => (float) Setting::get('barcode_paper_width', 4),
            'barcode_labels_per_row' => (int) Setting::get('barcode_labels_per_row', 1),
            'barcode_row_gap' => (float) Setting::get('barcode_row_gap', 0.3),
            'barcode_alignment' => (string) Setting::get('barcode_alignment', 'left'),
            'barcode_top_margin' => (float) Setting::get('barcode_top_margin', 0),
            'barcode_left_margin' => (float) Setting::get('barcode_left_margin', 0.5),
            'barcode_col_gap' => (float) Setting::get('barcode_col_gap', 0),

            'barcode_shop_name_size' => (int) Setting::get('barcode_shop_name_size', 6),
            'barcode_product_name_size' => (int) Setting::get('barcode_product_name_size', 7),
            'barcode_price_tag_size' => (int) Setting::get('barcode_price_tag_size', 9),
            'barcode_secret_code_size' => (int) Setting::get('barcode_secret_code_size', 8),
            'barcode_number_size' => (int) Setting::get('barcode_number_size', 8),

            'barcode_height' => (float) Setting::get('barcode_height', 0.7),
            'barcode_sticker_top_padding' => (float) Setting::get('barcode_sticker_top_padding', 0.1),
            'barcode_sticker_bottom_padding' => (float) Setting::get('barcode_sticker_bottom_padding', 0.1),

            'barcode_show_cost_code' => (bool) Setting::get('barcode_show_cost_code', false),
            'barcode_enable_selling_secret_code' => (bool) Setting::get('barcode_enable_selling_secret_code', false),
            'barcode_cost_code_map' => (array) Setting::get('barcode_cost_code_map', $defaultMap),
            'barcode_selling_code_map' => (array) Setting::get('barcode_selling_code_map', $defaultMap),
        ];

        $presets = (array) Setting::get('barcode_presets', []);
        $defaultPreset = (string) Setting::get('barcode_default_preset', '');

        $zeroFallback = (bool) config('app.secret_cost_zero_fallback', false);
        $sellingZeroFallback = (bool) config('app.secret_selling_zero_fallback', false);

        return view('settings.barcode', compact('settings', 'presets', 'defaultPreset', 'zeroFallback', 'sellingZeroFallback'));
    }

    /**
     * Save settings.
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'shop_name' => 'nullable|string|max:255',
            'shop_tagline' => 'nullable|string|max:255',
            'shop_address' => 'nullable|string|max:500',
            'shop_phone' => 'nullable|string|max:50',
            'shop_email' => 'nullable|email|max:255',
            'shop_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:1024',
            
            // General Settings
            'currency' => 'nullable|string|max:10',
            'currency_position' => 'nullable|in:before,after',
            'decimal_places' => 'nullable|integer|min:0|max:4',
            'date_format' => 'nullable|string|max:20',
            'time_format' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
            'items_per_page' => 'nullable|integer|min:5|max:100',
            'low_stock_warning' => 'nullable|boolean',
            // VAT Settings
            'vat_enabled' => 'nullable|boolean',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            
            // Invoice Settings
            'invoice_prefix' => 'nullable|string|max:20',
            'invoice_paper_size' => 'nullable|in:a4,80mm,58mm,letter',
            'invoice_show_logo' => 'nullable|boolean',
            'invoice_footer_text' => 'nullable|string|max:500',
            'invoice_terms' => 'nullable|string|max:2000',
            
            // Quotation Settings
            'quotation_prefix' => 'nullable|string|max:20',
            'quotation_valid_days' => 'nullable|integer|min:1|max:3650',
            'quotation_paper_size' => 'nullable|in:a4,80mm,58mm,letter',
            'quotation_show_logo' => 'nullable|boolean',
            'quotation_footer_text' => 'nullable|string|max:500',
            'quotation_terms' => 'nullable|string|max:2000',

            // POS Settings
            'pos_layout' => 'nullable|in:default,modern',

            // Card fee settings
            'pos_card_fee_enabled' => 'nullable|boolean',
            'pos_card_fee_rate' => 'nullable|numeric|min:0|max:100',
            'pos_card_fee_mode' => 'nullable|in:customer,seller',
            'pos_card_fee_record_expense' => 'nullable|boolean',
            'pos_card_fee_expense_category_id' => 'nullable|integer|min:0',

            // Barcode Settings
            'barcode_sticker_width' => 'nullable|numeric|min:0.1|max:100',
            'barcode_sticker_height' => 'nullable|numeric|min:0.1|max:100',
            'barcode_paper_width' => 'nullable|numeric|min:0.1|max:100',
            'barcode_labels_per_row' => 'nullable|integer|min:1|max:50',
            'barcode_row_gap' => 'nullable|numeric|min:0|max:10',
            'barcode_alignment' => 'nullable|in:left,center,right',
            'barcode_top_margin' => 'nullable|numeric|min:0|max:10',
            'barcode_left_margin' => 'nullable|numeric|min:0|max:10',
            'barcode_col_gap' => 'nullable|numeric|min:0|max:10',
            'barcode_shop_name_size' => 'nullable|integer|min:1|max:72',
            'barcode_product_name_size' => 'nullable|integer|min:1|max:72',
            'barcode_price_tag_size' => 'nullable|integer|min:1|max:72',
            'barcode_secret_code_size' => 'nullable|integer|min:1|max:72',
            'barcode_number_size' => 'nullable|integer|min:1|max:72',
            'barcode_height' => 'nullable|numeric|min:0.1|max:10',
            'barcode_sticker_top_padding' => 'nullable|numeric|min:0|max:10',
            'barcode_sticker_bottom_padding' => 'nullable|numeric|min:0|max:10',
            'barcode_show_cost_code' => 'nullable|boolean',
            'barcode_enable_selling_secret_code' => 'nullable|boolean',
            'barcode_cost_code' => 'nullable|array',
            'barcode_cost_code.*' => 'nullable|string|max:5',
            'barcode_selling_code' => 'nullable|array',
            'barcode_selling_code.*' => 'nullable|string|max:5',
            'barcode_preset_name' => 'nullable|string|max:50',
            'barcode_default_preset' => 'nullable|string|max:50',
        ]);

        $defaultMap = [
            '0' => 'E',
            '1' => 'M',
            '2' => 'O',
            '3' => 'D',
            '4' => 'T',
            '5' => 'W',
            '6' => 'I',
            '7' => 'N',
            '8' => 'K',
            '9' => 'L',
        ];

        if ($request->has('barcode_cost_code')) {
            $inputMap = (array) $request->input('barcode_cost_code', []);
            $mergedMap = $defaultMap;
            foreach ($inputMap as $digit => $code) {
                $digitKey = (string) $digit;
                if ($digitKey === '' || !array_key_exists($digitKey, $mergedMap)) {
                    continue;
                }
                $mergedMap[$digitKey] = (string) $code;
            }
            Setting::set('barcode_cost_code_map', $mergedMap, 'json', 'barcode');
        }

        if ($request->has('barcode_selling_code')) {
            $inputMap = (array) $request->input('barcode_selling_code', []);
            $mergedMap = $defaultMap;
            foreach ($inputMap as $digit => $code) {
                $digitKey = (string) $digit;
                if ($digitKey === '' || !array_key_exists($digitKey, $mergedMap)) {
                    continue;
                }
                $mergedMap[$digitKey] = (string) $code;
            }
            Setting::set('barcode_selling_code_map', $mergedMap, 'json', 'barcode');
        }

        $presetSettings = [
            'barcode_sticker_width' => (float) $request->input('barcode_sticker_width', 3),
            'barcode_sticker_height' => (float) $request->input('barcode_sticker_height', 2),
            'barcode_paper_width' => (float) $request->input('barcode_paper_width', 4),
            'barcode_labels_per_row' => (int) $request->input('barcode_labels_per_row', 1),
            'barcode_row_gap' => (float) $request->input('barcode_row_gap', 0.3),
            'barcode_alignment' => (string) $request->input('barcode_alignment', 'left'),
            'barcode_top_margin' => (float) $request->input('barcode_top_margin', 0),
            'barcode_left_margin' => (float) $request->input('barcode_left_margin', 0.5),
            'barcode_col_gap' => (float) $request->input('barcode_col_gap', 0),
            'barcode_shop_name_size' => (int) $request->input('barcode_shop_name_size', 6),
            'barcode_product_name_size' => (int) $request->input('barcode_product_name_size', 7),
            'barcode_price_tag_size' => (int) $request->input('barcode_price_tag_size', 9),
            'barcode_secret_code_size' => (int) $request->input('barcode_secret_code_size', 8),
            'barcode_number_size' => (int) $request->input('barcode_number_size', 8),
            'barcode_height' => (float) $request->input('barcode_height', 0.7),
            'barcode_sticker_top_padding' => (float) $request->input('barcode_sticker_top_padding', 0.1),
            'barcode_sticker_bottom_padding' => (float) $request->input('barcode_sticker_bottom_padding', 0.1),
            'barcode_show_cost_code' => (bool) $request->boolean('barcode_show_cost_code', false),
            'barcode_enable_selling_secret_code' => (bool) $request->boolean('barcode_enable_selling_secret_code', false),
            'barcode_cost_code_map' => (array) Setting::get('barcode_cost_code_map', $defaultMap),
            'barcode_selling_code_map' => (array) Setting::get('barcode_selling_code_map', $defaultMap),
        ];

        $presetName = trim((string) $request->input('barcode_preset_name', ''));
        if ($presetName !== '') {

            $presets = (array) Setting::get('barcode_presets', []);
            $updated = false;
            foreach ($presets as $idx => $preset) {
                if (($preset['name'] ?? '') === $presetName) {
                    $presets[$idx]['settings'] = $presetSettings;
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                $presets[] = [
                    'name' => $presetName,
                    'settings' => $presetSettings,
                ];
            }
            Setting::set('barcode_presets', array_values($presets), 'json', 'barcode');
        }

        $selectedDefaultPreset = trim((string) $request->input('barcode_default_preset', ''));
        if ($selectedDefaultPreset !== '') {
            $presets = (array) Setting::get('barcode_presets', []);
            $updatedDefaultPreset = false;
            foreach ($presets as $idx => $preset) {
                if (($preset['name'] ?? '') === $selectedDefaultPreset) {
                    $presets[$idx]['settings'] = $presetSettings;
                    $updatedDefaultPreset = true;
                    break;
                }
            }
            if ($updatedDefaultPreset) {
                Setting::set('barcode_presets', array_values($presets), 'json', 'barcode');
            }
        }

        unset($validated['barcode_preset_name'], $validated['barcode_cost_code'], $validated['barcode_selling_code']);

        foreach ($validated as $key => $value) {
            if ($key === 'shop_logo') { continue; }
            
            $type = 'text';
            $group = 'business';
            
            // Categorize settings
            if (in_array($key, ['low_stock_warning', 'invoice_show_logo', 'quotation_show_logo'])) {
                $type = 'boolean';
            }
            
            if (in_array($key, ['currency', 'currency_position', 'decimal_places', 'date_format', 'time_format', 'timezone', 'language', 'items_per_page', 'low_stock_warning', 'vat_enabled', 'vat_rate'])) {
                $group = 'general';
            } elseif (in_array($key, ['invoice_prefix', 'invoice_paper_size', 'invoice_show_logo', 'invoice_footer_text', 'invoice_terms'])) {
                $group = 'invoice';
            } elseif (in_array($key, ['quotation_prefix', 'quotation_valid_days', 'quotation_paper_size', 'quotation_show_logo', 'quotation_footer_text', 'quotation_terms'])) {
                $group = 'quotation';
            } elseif (str_starts_with($key, 'pos_')) {
                $group = 'pos';
            } elseif (str_starts_with($key, 'barcode_')) {
                $group = 'barcode';
            }

            if (in_array($key, ['low_stock_warning', 'invoice_show_logo', 'quotation_show_logo', 'vat_enabled', 'pos_card_fee_enabled', 'pos_card_fee_record_expense', 'barcode_show_cost_code', 'barcode_enable_selling_secret_code'])) {
                $type = 'boolean';
            }

            if (in_array($key, ['vat_rate', 'pos_card_fee_rate'])) {
                $type = 'number';
            }

            if (str_starts_with($key, 'barcode_') && $type === 'number') {
                $type = 'text';
            }

            if (is_array($value)) {
                $type = 'json';
            }
            
            Setting::set($key, $value, $type, $group);
        }

        if ($request->hasFile('shop_logo')) {
            $file = $request->file('shop_logo');
            $path = $file->store('logos', 'public'); // e.g., logos/filename.png

            // Also copy to public/logos to avoid storage:link dependency
            $src = Storage::disk('public')->path($path);
            $destDir = public_path('logos');
            if (!is_dir($destDir)) { @mkdir($destDir, 0755, true); }
            $dest = $destDir.'/'.basename($path);
            @copy($src, $dest);

            // Save relative path usable with asset($path)
            Setting::set('shop_logo', $path, 'text', 'business');
        }

        return back()->with('success', 'Settings saved successfully!');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
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
        ]);

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
            }

            if (in_array($key, ['low_stock_warning', 'invoice_show_logo', 'quotation_show_logo', 'vat_enabled'])) {
                $type = 'boolean';
            }

            if (in_array($key, ['vat_rate'])) {
                $type = 'number';
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

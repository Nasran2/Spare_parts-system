<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\Setting;
use App\Models\Sale;
use App\Models\Purchase;

class InformationController extends Controller
{
    /**
     * Show the visiting card page using business settings.
     */
    public function card()
    {
        $settings = [
            'shop_name' => Setting::get('shop_name', 'Vehicle POS System'),
            'shop_tagline' => Setting::get('shop_tagline', 'Auto Parts System'),
            'shop_address' => Setting::get('shop_address', ''),
            'shop_phone' => Setting::get('shop_phone', ''),
            'shop_email' => Setting::get('shop_email', ''),
            'shop_logo' => Setting::get('shop_logo', ''),
        ];

        return view('secretpos.card', compact('settings'));
    }

    /**
     * Verify 4-digit code from popup and set session.
     */
    public function login(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:4',
        ]);

        $secretCode = (string) Setting::get('secretpos.code', '0000');
        if ($request->input('code') !== $secretCode) {
            return back()->withErrors(['code' => 'Invalid code'])->withInput();
        }

        Session::put('secretpos.auth', true);
        return redirect()->route('information.secret');
    }

    /**
     * Secret page showing configuration options.
     */
    public function secret(Request $request)
    {
        if (!Session::get('secretpos.auth')) {
            return redirect()->route('information.card');
        }

        // Date filters for calculating actual totals and range stats
        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $config = [
            'code' => Setting::get('secretpos.code', '0000'),
            'override_total_sales_amount' => (float) Setting::get('secretpos.override_total_sales_amount', 0),
            'override_sales_count' => (int) Setting::get('secretpos.override_sales_count', 0),
            'hidden_ranges_sales' => Setting::get('secretpos.hidden_ranges_sales', Setting::get('secretpos.hidden_ranges', [
                ['min' => 0, 'max' => 100000, 'hide' => false],
                ['min' => 100001, 'max' => 300000, 'hide' => false],
                ['min' => 300001, 'max' => 600000, 'hide' => false],
            ])),
            'hidden_ranges_purchases' => Setting::get('secretpos.hidden_ranges_purchases', [
                ['min' => 0, 'max' => 100000, 'hide' => false],
                ['min' => 100001, 'max' => 300000, 'hide' => false],
                ['min' => 300001, 'max' => 600000, 'hide' => false],
            ]),
            'force_error_mode' => (bool) Setting::get('secretpos.force_error_mode', false),
        ];

        // Build base query to compute actual numbers (include all bills, hidden or not)
        $q = Sale::query();
        // Exclude quotations if any
        $q->where(function($x){
            $x->whereNull('sale_type')->orWhere('sale_type', '!=', 'quotation');
        });
        if (!empty($filters['date_from'])) {
            $q->whereDate('sale_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->whereDate('sale_date', '<=', $filters['date_to']);
        }

        $actual = [
            'count' => (clone $q)->count(),
            'amount' => (float) (clone $q)->sum('total_amount'),
        ];

        // Per-range counts for quick view
        $salesRangeCounts = [];
        foreach ((array) $config['hidden_ranges_sales'] as $idx => $r) {
            $min = (int) ($r['min'] ?? 0);
            $max = (int) ($r['max'] ?? PHP_INT_MAX);
            $salesRangeCounts[$idx] = (clone $q)->whereBetween('total_amount', [$min, $max])->count();
        }

        $purchaseBase = Purchase::query();
        if (!empty($filters['date_from'])) {
            $purchaseBase->whereDate('purchase_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $purchaseBase->whereDate('purchase_date', '<=', $filters['date_to']);
        }
        $purchaseRangeCounts = [];
        foreach ((array) $config['hidden_ranges_purchases'] as $idx => $r) {
            $min = (int) ($r['min'] ?? 0);
            $max = (int) ($r['max'] ?? PHP_INT_MAX);
            $purchaseRangeCounts[$idx] = (clone $purchaseBase)->whereBetween('total_amount', [$min, $max])->count();
        }

        return view('secretpos.secret', [
            'config' => $config,
            'filters' => $filters,
            'actual' => $actual,
            'salesRangeCounts' => $salesRangeCounts,
            'purchaseRangeCounts' => $purchaseRangeCounts,
        ]);
    }

    /**
     * Save secret configuration.
     */
    public function save(Request $request)
    {
        if (!Session::get('secretpos.auth')) {
            return redirect()->route('information.card');
        }

        $validated = $request->validate([
            'code' => 'required|digits:4',
            'override_total_sales_amount' => 'nullable|numeric|min:0',
            'override_sales_count' => 'nullable|integer|min:0',
            'force_error_mode' => 'nullable|boolean',
            'sales_ranges' => 'nullable|array',
            'sales_ranges.*.min' => 'required_with:sales_ranges|integer|min:0',
            'sales_ranges.*.max' => 'required_with:sales_ranges|integer|min:0',
            'sales_ranges.*.hide' => 'required_with:sales_ranges|boolean',
            'purchase_ranges' => 'nullable|array',
            'purchase_ranges.*.min' => 'required_with:purchase_ranges|integer|min:0',
            'purchase_ranges.*.max' => 'required_with:purchase_ranges|integer|min:0',
            'purchase_ranges.*.hide' => 'required_with:purchase_ranges|boolean',
        ]);

        // Persist settings
        Setting::set('secretpos.code', $validated['code'], 'text', 'secretpos');
        Setting::set('secretpos.override_total_sales_amount', $validated['override_total_sales_amount'] ?? 0, 'number', 'secretpos');
        Setting::set('secretpos.override_sales_count', $validated['override_sales_count'] ?? 0, 'number', 'secretpos');
        Setting::set('secretpos.force_error_mode', (bool) ($validated['force_error_mode'] ?? false), 'boolean', 'secretpos');

        // Hidden ranges: Sales
        $salesRanges = $validated['sales_ranges'] ?? [];
        $salesNormalized = [];
        foreach ($salesRanges as $r) {
            $salesNormalized[] = [
                'min' => (int) $r['min'],
                'max' => (int) $r['max'],
                'hide' => (bool) $r['hide'],
            ];
        }
        Setting::set('secretpos.hidden_ranges_sales', $salesNormalized, 'json', 'secretpos');
        // Legacy key for older code paths
        Setting::set('secretpos.hidden_ranges', $salesNormalized, 'json', 'secretpos');

        // Hidden ranges: Purchases
        $purchaseRanges = $validated['purchase_ranges'] ?? [];
        $purchaseNormalized = [];
        foreach ($purchaseRanges as $r) {
            $purchaseNormalized[] = [
                'min' => (int) $r['min'],
                'max' => (int) $r['max'],
                'hide' => (bool) $r['hide'],
            ];
        }
        Setting::set('secretpos.hidden_ranges_purchases', $purchaseNormalized, 'json', 'secretpos');

        return back()->with('success', 'Secret settings saved');
    }

    /**
     * AJAX: Return bills list HTML for a given amount range and optional date filters.
     */
    public function rangeBills(Request $request)
    {
        if (!Session::get('secretpos.auth')) {
            abort(403);
        }

        $data = $request->validate([
            'type' => 'nullable|in:sale,purchase',
            'min' => 'required|numeric|min:0',
            'max' => 'required|numeric|min:0',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $type = $data['type'] ?? 'sale';

        if ($type === 'purchase') {
            $q = Purchase::with('supplier');
            $q->whereBetween('total_amount', [ (float)$data['min'], (float)$data['max'] ]);
            if (!empty($data['date_from'])) {
                $q->whereDate('purchase_date', '>=', $data['date_from']);
            }
            if (!empty($data['date_to'])) {
                $q->whereDate('purchase_date', '<=', $data['date_to']);
            }

            $bills = $q->orderByDesc('purchase_date')->limit(300)->get();
            $currency = config('app.currency', 'Rs ');
            $html = view('secretpos.partials.range-purchases', compact('bills', 'currency'))->render();
            return response()->json(['html' => $html]);
        }

        $q = Sale::with('customer');
        $q->where(function($x){
            $x->whereNull('sale_type')->orWhere('sale_type', '!=', 'quotation');
        });
        $q->whereBetween('total_amount', [ (float)$data['min'], (float)$data['max'] ]);
        if (!empty($data['date_from'])) {
            $q->whereDate('sale_date', '>=', $data['date_from']);
        }
        if (!empty($data['date_to'])) {
            $q->whereDate('sale_date', '<=', $data['date_to']);
        }

        $bills = $q->orderByDesc('sale_date')->limit(300)->get();
        $currency = config('app.currency', 'Rs ');
        $html = view('secretpos.partials.range-bills', compact('bills', 'currency'))->render();
        return response()->json(['html' => $html]);
    }
}

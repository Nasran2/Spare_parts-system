<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\DashboardVisibilityService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Load brands and return index view
        $brands = Brand::query()
            ->orderBy('created_at', 'desc')
            ->get();

        $metrics = Brand::productMetricsMap($brands->pluck('id')->all());

        foreach ($brands as $brand) {
            $brandMetric = $metrics->get($brand->id);
            $brand->products_count = (int) ($brandMetric->products_count ?? 0);
            $brand->total_cost_price = (float) ($brandMetric->total_cost_price ?? 0);
            $brand->total_selling_price = (float) ($brandMetric->total_selling_price ?? 0);
        }

        $controls = DashboardVisibilityService::configForUser($request->user());

        return view('brands.index', compact('brands', 'controls'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('brands.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'description' => 'nullable|string',
        ]);

        $brand = Brand::create($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'brand' => $brand,
                'message' => 'Brand created successfully!',
            ]);
        }

        return redirect()->route('brands.index')
            ->with('success', 'Brand created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $brand = Brand::findOrFail($id);

        return view('brands.show', compact('brand'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $brand = Brand::findOrFail($id);

        return view('brands.edit', compact('brand'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $brand = Brand::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,'.$brand->id,
            'description' => 'nullable|string',
        ]);

        $brand->update($validated);

        return redirect()->route('brands.index')->with('success', 'Brand updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $brand = Brand::findOrFail($id);
        $brand->delete();

        return redirect()->route('brands.index')->with('success', 'Brand deleted successfully!');
    }
}

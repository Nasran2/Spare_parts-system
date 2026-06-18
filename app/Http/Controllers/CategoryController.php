<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\DashboardVisibilityService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Load active categories and show index view
        $categories = Category::query()
            ->with('parent:id,name')
            ->orderByRaw('parent_id IS NULL DESC')
            ->orderBy('name')
            ->get();

        $baseMetrics = Category::productMetricsMap($categories->pluck('id')->all());
        $childrenByParent = $categories
            ->whereNotNull('parent_id')
            ->groupBy('parent_id');

        foreach ($categories as $category) {
            $selfMetric = $baseMetrics->get($category->id);
            $selfCount = (int) ($selfMetric->products_count ?? 0);
            $selfTotalCost = (float) ($selfMetric->total_cost_price ?? 0);
            $selfTotalSelling = (float) ($selfMetric->total_selling_price ?? 0);

            if (empty($category->parent_id)) {
                $childIds = ($childrenByParent->get($category->id) ?? collect())->pluck('id')->all();
                $childTotal = 0;
                $childTotalCost = 0;
                $childTotalSelling = 0;
                foreach ($childIds as $childId) {
                    $childMetric = $baseMetrics->get($childId);
                    $childTotal += (int) ($childMetric->products_count ?? 0);
                    $childTotalCost += (float) ($childMetric->total_cost_price ?? 0);
                    $childTotalSelling += (float) ($childMetric->total_selling_price ?? 0);
                }
                $category->products_count = $selfCount + $childTotal;
                $category->total_cost_price = $selfTotalCost + $childTotalCost;
                $category->total_selling_price = $selfTotalSelling + $childTotalSelling;
            } else {
                $category->products_count = $selfCount;
                $category->total_cost_price = $selfTotalCost;
                $category->total_selling_price = $selfTotalSelling;
            }
        }

        $controls = DashboardVisibilityService::configForUser($request->user());

        return view('categories.index', compact('categories', 'controls'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $parents = Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('categories.create', compact('parents'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:categories,id',
        ]);

        $category = Category::create($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'category' => $category,
                'message' => 'Category created successfully!',
            ]);
        }

        return redirect()->route('categories.index')
            ->with('success', 'Category created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = Category::findOrFail($id);

        return view('categories.show', compact('category'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $category = Category::findOrFail($id);
        $parents = Category::query()
            ->whereNull('parent_id')
            ->where('id', '!=', $category->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('categories.edit', compact('category', 'parents'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,'.$category->id,
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:categories,id|not_in:'.$category->id,
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')->with('success', 'Category updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Category deleted successfully!');
    }

    /**
     * Return active sub-categories (children) of a given category.
     */
    public function children(Category $category)
    {
        $children = $category->children()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $counts = Category::productCountsMap($children->pluck('id')->all());
        $payload = $children->map(fn ($child) => [
            'id' => $child->id,
            'name' => $child->name,
            'parent_id' => $child->parent_id,
            'products_count' => (int) ($counts[$child->id] ?? 0),
        ]);

        return response()->json([
            'success' => true,
            'children' => $payload,
        ]);
    }
}

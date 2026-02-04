<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = ExpenseCategory::orderBy('created_at','desc')->get();
        return view('expense-categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('expense-categories.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories,name',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'limit' => 'nullable|numeric|min:0',
            'reset_frequency' => 'nullable|in:lifetime,monthly',
            'reset_date' => 'nullable|integer|min:1|max:31',
        ]);

        if(!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $category = ExpenseCategory::create($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'category' => $category,
                'message' => 'Expense category created successfully!'
            ]);
        }

        return redirect()->route('expense-categories.index')
            ->with('success', 'Expense category created successfully!');
    }

    // Show method not used (route excluded) - removed to prevent missing view errors

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $category = ExpenseCategory::findOrFail($id);
        return view('expense-categories.edit', compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $category = ExpenseCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories,name,' . $category->id,
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'limit' => 'nullable|numeric|min:0',
            'reset_frequency' => 'nullable|in:lifetime,monthly',
            'reset_date' => 'nullable|integer|min:1|max:31',
        ]);

        if(!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $category->update($validated);

        return redirect()->route('expense-categories.index')->with('success', 'Expense category updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = ExpenseCategory::findOrFail($id);
        $category->delete();
        return redirect()->route('expense-categories.index')->with('success', 'Expense category deleted successfully!');
    }
}

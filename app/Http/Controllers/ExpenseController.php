<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $expenses = Expense::with('category')->latest()->paginate(20);
        $categories = ExpenseCategory::where('is_active', true)->get();
        return view('expenses.index', compact('expenses', 'categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = ExpenseCategory::where('is_active', true)->get();
        return view('expenses.create', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // Check Limit
        $category = ExpenseCategory::find($validated['expense_category_id']);
        if ($category->limit > 0) {
            $query = Expense::where('expense_category_id', $category->id);

            if ($category->reset_frequency === 'monthly') {
                $expenseDate = Carbon::parse($validated['expense_date']);
                $resetDay = $category->reset_date ?? 1;
                
                $startDate = $expenseDate->copy();
                if ($expenseDate->day < $resetDay) {
                    $startDate->subMonth();
                }
                // Handle months with fewer days
                $startDate->day = min($resetDay, $startDate->daysInMonth);
                
                $query->where('expense_date', '>=', $startDate->format('Y-m-d'));
            }

            $currentTotal = $query->sum('amount');
            
            if (($currentTotal + $validated['amount']) > $category->limit) {
                return back()->withInput()->withErrors(['amount' => "Expense limit reached for this category. Limit: {$category->limit}, Current Total: {$currentTotal}, Remaining: " . max(0, $category->limit - $currentTotal)]);
            }
        }

        if ($request->hasFile('receipt')) {
            $validated['receipt'] = $request->file('receipt')->store('receipts', 'public');
        }

        $validated['user_id'] = $request->user()->id;

        Expense::create($validated);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        $expense->load('category', 'user');
        return view('expenses.show', compact('expense'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Expense $expense)
    {
        $categories = ExpenseCategory::where('is_active', true)->get();
        return view('expenses.edit', compact('expense', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // Check Limit
        $category = ExpenseCategory::find($validated['expense_category_id']);
        if ($category->limit > 0) {
            $query = Expense::where('expense_category_id', $category->id)
                            ->where('id', '!=', $expense->id); // Exclude current expense

            if ($category->reset_frequency === 'monthly') {
                $expenseDate = Carbon::parse($validated['expense_date']);
                $resetDay = $category->reset_date ?? 1;
                
                $startDate = $expenseDate->copy();
                if ($expenseDate->day < $resetDay) {
                    $startDate->subMonth();
                }
                $startDate->day = min($resetDay, $startDate->daysInMonth);
                
                $query->where('expense_date', '>=', $startDate->format('Y-m-d'));
            }

            $currentTotal = $query->sum('amount');
            
            if (($currentTotal + $validated['amount']) > $category->limit) {
                return back()->withInput()->withErrors(['amount' => "Expense limit reached for this category. Limit: {$category->limit}, Current Total: {$currentTotal}, Remaining: " . max(0, $category->limit - $currentTotal)]);
            }
        }

        if ($request->hasFile('receipt')) {
            if ($expense->receipt) {
                Storage::disk('public')->delete($expense->receipt);
            }
            $validated['receipt'] = $request->file('receipt')->store('receipts', 'public');
        }

        $expense->update($validated);

        return redirect()->route('expenses.index')->with('success', 'Expense updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $expense = Expense::findOrFail($id);
        if ($expense->receipt) {
            Storage::disk('public')->delete($expense->receipt);
        }
        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'Expense deleted');
    }
}

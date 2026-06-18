<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseAccountingService;
use App\Support\PublicStorageSync;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            'store_id' => 'nullable|exists:stores,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,bank_transfer',
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
                return back()->withInput()->withErrors(['amount' => "Expense limit reached for this category. Limit: {$category->limit}, Current Total: {$currentTotal}, Remaining: ".max(0, $category->limit - $currentTotal)]);
            }
        }

        if ($request->hasFile('receipt')) {
            $validated['receipt'] = $request->file('receipt')->store('receipts', 'public');
            PublicStorageSync::syncFile($validated['receipt']);
        }

        $validated['user_id'] = $request->user()->id;

        DB::transaction(function () use ($validated, $request) {
            $expense = Expense::create($validated);
            app(ExpenseAccountingService::class)->sync($expense->load('category'), $request->user()->id);
        });

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
            'store_id' => 'nullable|exists:stores,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,bank_transfer',
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
                return back()->withInput()->withErrors(['amount' => "Expense limit reached for this category. Limit: {$category->limit}, Current Total: {$currentTotal}, Remaining: ".max(0, $category->limit - $currentTotal)]);
            }
        }

        if ($request->hasFile('receipt')) {
            if ($expense->receipt) {
                Storage::disk('public')->delete($expense->receipt);
                PublicStorageSync::removeFile($expense->receipt);
            }
            $validated['receipt'] = $request->file('receipt')->store('receipts', 'public');
            PublicStorageSync::syncFile($validated['receipt']);
        }

        DB::transaction(function () use ($expense, $validated, $request) {
            $expense->update($validated);
            app(ExpenseAccountingService::class)->sync($expense->fresh('category'), $request->user()?->id);
        });

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
            PublicStorageSync::removeFile($expense->receipt);
        }
        DB::transaction(function () use ($expense) {
            app(ExpenseAccountingService::class)->reverse($expense);
            $expense->delete();
        });

        return redirect()->route('expenses.index')->with('success', 'Expense deleted');
    }
}

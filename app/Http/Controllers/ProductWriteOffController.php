<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ProductWriteOffController extends Controller
{
    public function index()
    {
        $products = Product::where('is_active', true)->where('stock_quantity', '>', 0)->get();
        // Get recent write-offs (expenses with category 'Product Write-off')
        $writeOffCategory = ExpenseCategory::where('name', 'Product Write-off')->first();
        $recentWriteOffs = [];
        
        if ($writeOffCategory) {
            $recentWriteOffs = Expense::where('expense_category_id', $writeOffCategory->id)
                ->with('user')
                ->latest('expense_date')
                ->take(10)
                ->get();
        }

        return view('products.write-off', compact('products', 'recentWriteOffs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        $product = Product::findOrFail($request->product_id);

        if ($product->stock_quantity < $request->quantity) {
            return back()->withErrors(['quantity' => 'Insufficient stock quantity. Available: ' . $product->stock_quantity]);
        }

        DB::beginTransaction();

        try {
            // 1. Decrement Stock
            $product->decrement('stock_quantity', $request->quantity);

            // 2. Calculate Cost Amount
            $costAmount = $product->cost_price * $request->quantity;

            // 3. Find or Create Expense Category
            $category = ExpenseCategory::firstOrCreate(
                ['name' => 'Product Write-off'],
                ['description' => 'Expenses related to damaged or expired products', 'is_active' => true]
            );

            // Check Limit
            if ($category->limit > 0) {
                $query = Expense::where('expense_category_id', $category->id);
                if ($category->reset_frequency === 'monthly') {
                    $expenseDate = Carbon::parse($request->date);
                    $resetDay = $category->reset_date ?? 1;
                    $startDate = $expenseDate->copy();
                    if ($expenseDate->day < $resetDay) {
                        $startDate->subMonth();
                    }
                    $startDate->day = min($resetDay, $startDate->daysInMonth);
                    $query->where('expense_date', '>=', $startDate->format('Y-m-d'));
                }
                $currentTotal = $query->sum('amount');
                if (($currentTotal + $costAmount) > $category->limit) {
                     throw new \Exception("Expense limit reached for 'Product Write-off'. Limit: {$category->limit}, Remaining: " . max(0, $category->limit - $currentTotal));
                }
            }

            // 4. Create Expense Record
            Expense::create([
                'expense_category_id' => $category->id,
                'user_id' => Auth::id(),
                'expense_date' => $request->date,
                'amount' => $costAmount,
                'description' => "Write-off: {$request->quantity} x {$product->name} (Reason: {$request->reason})",
            ]);

            // 5. Log Activity
            ActivityLog::log('write-off', "Wrote off {$request->quantity} of {$product->name}", $product);

            DB::commit();

            return redirect()->route('products.write-off.index')->with('success', 'Product write-off recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error recording write-off: ' . $e->getMessage());
        }
    }
}

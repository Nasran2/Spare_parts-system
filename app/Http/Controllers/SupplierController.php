<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\DashboardVisibilityService;
use App\Support\SecretPos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $hiddenSupplierIds = DashboardVisibilityService::hiddenSupplierIdsForUser($request->user());
        $suppliers = Supplier::query()
            ->when(! empty($hiddenSupplierIds), fn ($query) => $query->whereNotIn('id', $hiddenSupplierIds))
            ->orderBy('name')
            ->get();
        $controls = DashboardVisibilityService::configForUser($request->user());

        return view('suppliers.index', compact('suppliers', 'controls'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('suppliers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'company_name' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255|unique:suppliers,email',
                'phone' => 'required|string|max:30',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'opening_balance' => 'nullable|numeric|min:0',
                'is_active' => 'nullable',
            ]);

            $validated['is_active'] = $request->boolean('is_active');
            $validated['opening_balance'] = $validated['opening_balance'] ?? 0;

            $supplier = Supplier::create($validated);

            // Return JSON for AJAX requests (quick-create modal)
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Supplier created successfully!',
                    'supplier' => $supplier,
                ]);
            }

            return redirect()->route('suppliers.index')
                ->with('success', 'Supplier created successfully!');
        } catch (\Throwable $e) {
            Log::error('Supplier create failed', ['error' => $e->getMessage()]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withInput()->with('error', 'Failed to create supplier.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $supplier = Supplier::findOrFail($id);
        if (DashboardVisibilityService::isSupplierHiddenForUser((int) $supplier->id, $request->user())) {
            abort(404);
        }
        $controls = DashboardVisibilityService::configForUser($request->user());

        $purchasesQuery = $supplier->purchases()->with('payments')->orderByDesc('purchase_date');
        $purchasesQuery = SecretPos::excludeHiddenPurchaseRanges($purchasesQuery, 'total_amount');
        $purchases = $purchasesQuery->get();

        $purchaseTotals = [
            'total_purchases' => (float) $purchases->sum('total_amount'),
            'total_due' => (float) $purchases->sum('due_amount'),
        ];

        return view('suppliers.show', compact('supplier', 'purchases', 'purchaseTotals', 'controls'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $supplier = Supplier::findOrFail($id);
        if (DashboardVisibilityService::isSupplierHiddenForUser((int) $supplier->id, auth()->user())) {
            abort(404);
        }

        return view('suppliers.edit', compact('supplier'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $supplier = Supplier::findOrFail($id);
        if (DashboardVisibilityService::isSupplierHiddenForUser((int) $supplier->id, auth()->user())) {
            abort(404);
        }
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'company_name' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255|unique:suppliers,email,'.$supplier->id,
                'phone' => 'required|string|max:30',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'opening_balance' => 'nullable|numeric|min:0',
                'is_active' => 'nullable',
            ]);

            $validated['is_active'] = $request->boolean('is_active');
            $validated['opening_balance'] = $validated['opening_balance'] ?? 0;

            $supplier->update($validated);

            return redirect()->route('suppliers.index')
                ->with('success', 'Supplier updated successfully!');
        } catch (\Throwable $e) {
            Log::error('Supplier update failed', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Failed to update supplier.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $supplier = Supplier::findOrFail($id);
        if (DashboardVisibilityService::isSupplierHiddenForUser((int) $supplier->id, auth()->user())) {
            abort(404);
        }
        try {
            // TODO: If purchases exist, consider preventing deletion or soft deleting
            $supplier->delete();

            return redirect()->route('suppliers.index')
                ->with('success', 'Supplier deleted successfully!');
        } catch (\Throwable $e) {
            Log::error('Supplier delete failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Failed to delete supplier.');
        }
    }
}

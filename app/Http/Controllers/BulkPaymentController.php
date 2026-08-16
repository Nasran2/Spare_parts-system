<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Services\DashboardVisibilityService;
use App\Support\SecretPos;

class BulkPaymentController extends Controller
{
    /**
     * Display the bulk payment allocation page for a customer.
     */
    public function customerPayment(Request $request, Customer $customer)
    {
        $controls = DashboardVisibilityService::configForUser($request->user());
        if (DashboardVisibilityService::isCustomerHiddenForUser((int) $customer->id, $request->user())) {
            abort(404);
        }

        // Get unpaid sales (invoices)
        $unpaidSales = $customer->sales()
            ->where('due_amount', '>', 0)
            ->where('sale_type', 'sale')
            ->orderBy('sale_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->reject(fn ($s) => SecretPos::isHidden((float) $s->total_amount)
                || DashboardVisibilityService::isSaleHiddenForUser((int) $s->id, $request->user()));

        // Calculate generic payments (payments without sale_id)
        $genericPayments = $customer->payments()->whereNull('sale_id')->sum('amount');
        
        // Opening balance due
        $openingBalanceDue = max(0, $customer->opening_balance - $genericPayments);

        return view('payments.bulk-customer', compact('customer', 'unpaidSales', 'openingBalanceDue', 'controls'));
    }

    /**
     * Store the bulk payment for a customer.
     */
    public function storeCustomerPayment(Request $request, Customer $customer)
    {
        $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'allocations' => 'required|array',
            'allocations.*.type' => 'required|in:opening_balance,sale',
            'allocations.*.id' => 'nullable|integer',
            'allocations.*.amount' => 'required|numeric|min:0',
        ]);

        if (DashboardVisibilityService::isCustomerHiddenForUser((int) $customer->id, $request->user())) {
            abort(404);
        }

        foreach ($request->allocations as $alloc) {
            $amount = round((float) $alloc['amount'], 2);
            if ($amount <= 0) continue;

            if ($alloc['type'] === 'opening_balance') {
                Payment::create([
                    'customer_id' => $customer->id,
                    'amount' => $amount,
                    'payment_method' => $request->payment_method,
                    'payment_date' => $request->payment_date,
                    'notes' => 'Payment towards Opening Balance',
                ]);
            } elseif ($alloc['type'] === 'sale' && !empty($alloc['id'])) {
                $sale = Sale::where('id', $alloc['id'])->where('customer_id', $customer->id)->first();
                if ($sale) {
                    Payment::create([
                        'customer_id' => $customer->id,
                        'sale_id' => $sale->id,
                        'amount' => $amount,
                        'payment_method' => $request->payment_method,
                        'payment_date' => $request->payment_date,
                        'notes' => 'Bulk Payment Allocation',
                    ]);

                    // Update Sale due amount
                    $sale->paid_amount += $amount;
                    $sale->due_amount = max(0, $sale->total_amount - $sale->paid_amount);
                    $sale->payment_status = $sale->due_amount > 0 ? 'partial' : 'paid';
                    $sale->save();
                }
            }
        }

        return redirect()->route('customers.show', $customer->id)->with('success', 'Payment processed successfully.');
    }

    /**
     * Display the bulk payment allocation page for a supplier.
     */
    public function supplierPayment(Request $request, Supplier $supplier)
    {
        $controls = DashboardVisibilityService::configForUser($request->user());
        if (DashboardVisibilityService::isSupplierHiddenForUser((int) $supplier->id, $request->user())) {
            abort(404);
        }

        // Get unpaid purchases
        $unpaidPurchases = $supplier->purchases()
            ->where('due_amount', '>', 0)
            ->orderBy('purchase_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->reject(fn ($p) => SecretPos::isPurchaseHidden((float) $p->total_amount));

        // Calculate generic payments (payments without purchase_id)
        $genericPayments = $supplier->payments()->whereNull('purchase_id')->sum('amount');
        
        // Opening balance due
        $openingBalanceDue = max(0, $supplier->opening_balance - $genericPayments);

        return view('payments.bulk-supplier', compact('supplier', 'unpaidPurchases', 'openingBalanceDue', 'controls'));
    }

    /**
     * Store the bulk payment for a supplier.
     */
    public function storeSupplierPayment(Request $request, Supplier $supplier)
    {
        $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'allocations' => 'required|array',
            'allocations.*.type' => 'required|in:opening_balance,purchase',
            'allocations.*.id' => 'nullable|integer',
            'allocations.*.amount' => 'required|numeric|min:0',
        ]);

        if (DashboardVisibilityService::isSupplierHiddenForUser((int) $supplier->id, $request->user())) {
            abort(404);
        }

        foreach ($request->allocations as $alloc) {
            $amount = round((float) $alloc['amount'], 2);
            if ($amount <= 0) continue;

            if ($alloc['type'] === 'opening_balance') {
                Payment::create([
                    'supplier_id' => $supplier->id,
                    'amount' => $amount,
                    'payment_method' => $request->payment_method,
                    'payment_date' => $request->payment_date,
                    'notes' => 'Payment towards Opening Balance',
                ]);
            } elseif ($alloc['type'] === 'purchase' && !empty($alloc['id'])) {
                $purchase = Purchase::where('id', $alloc['id'])->where('supplier_id', $supplier->id)->first();
                if ($purchase) {
                    Payment::create([
                        'supplier_id' => $supplier->id,
                        'purchase_id' => $purchase->id,
                        'amount' => $amount,
                        'payment_method' => $request->payment_method,
                        'payment_date' => $request->payment_date,
                        'notes' => 'Bulk Payment Allocation',
                    ]);

                    // Update Purchase due amount
                    $purchase->paid_amount += $amount;
                    $purchase->due_amount = max(0, $purchase->total_amount - $purchase->paid_amount);
                    $purchase->payment_status = $purchase->due_amount > 0 ? 'partial' : 'paid';
                    $purchase->save();
                }
            }
        }

        return redirect()->route('suppliers.show', $supplier->id)->with('success', 'Payment processed successfully.');
    }
}

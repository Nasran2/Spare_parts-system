<?php

namespace App\Http\Controllers;

use App\Models\ChequePayment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\BulkPaymentAccountingService;
use App\Services\DashboardVisibilityService;
use App\Support\SecretPos;
use Illuminate\Http\Request;

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
            'cheque_number' => 'nullable|required_if:payment_method,cheque|string',
            'bank_name' => 'nullable|required_if:payment_method,cheque|string',
            'cheque_date' => 'nullable|required_if:payment_method,cheque|date',
        ]);

        if (DashboardVisibilityService::isCustomerHiddenForUser((int) $customer->id, $request->user())) {
            abort(404);
        }

        $bank = \App\Support\PaymentBankAccount::resolve($request->payment_method, $request->input('bank_account_id'));
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $customer, $bank) {
            foreach ($request->allocations as $alloc) {
                $amount = round((float) $alloc['amount'], 2);
                if ($amount <= 0) {
                    continue;
                }
                $sale = $alloc['type'] === 'sale'
                    ? Sale::whereKey($alloc['id'])->where('customer_id', $customer->id)->lockForUpdate()->firstOrFail()
                    : null;
                if ($request->payment_method === 'cheque') {
                    $cheque = ChequePayment::create([
                        'customer_id' => $customer->id, 'sale_id' => $sale?->id,
                        'user_id' => $request->user()->id, 'bank_account_id' => $bank?->id,
                        'cheque_date' => $request->cheque_date, 'cheque_number' => $request->cheque_number,
                        'bank_name' => $request->bank_name, 'account_name' => $request->account_name,
                        'amount' => $amount, 'status' => 'pending',
                        'notes' => $sale ? 'Allocated to Sale '.$sale->sale_no : 'Allocated to Opening Balance',
                    ]);
                    if ($sale) {
                        $sale->held_cheque_amount = (float) $sale->held_cheque_amount + $amount;
                        app(\App\Services\SalePaymentAccountingService::class)->recordChequeHold($cheque, $sale, $request->user()->id);
                    }
                } else {
                    $payment = Payment::create([
                        'customer_id' => $customer->id, 'sale_id' => $sale?->id,
                        'user_id' => $request->user()->id, 'amount' => $amount,
                        'payment_method' => $request->payment_method, 'bank_account_id' => $bank?->id,
                        'payment_date' => $request->payment_date,
                        'notes' => $sale ? 'Bulk Payment Allocation' : 'Payment towards Opening Balance',
                    ]);
                    app(BulkPaymentAccountingService::class)->recordCustomerPayment($payment, $request->user()->id);
                    if ($sale) {
                        $sale->paid_amount = (float) $sale->paid_amount + $amount;
                    }
                }
                if ($sale) {
                    $sale->due_amount = max(0, (float) $sale->total_amount - (float) $sale->paid_amount - (float) $sale->held_cheque_amount);
                    $sale->payment_status = $sale->due_amount <= 0 && $sale->held_cheque_amount <= 0 ? 'paid' : 'partial';
                    $sale->save();
                }
            }
        });

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
            if ($amount <= 0) {
                continue;
            }

            if ($alloc['type'] === 'opening_balance') {
                $payment = Payment::create([
                    'supplier_id' => $supplier->id,
                    'amount' => $amount,
                    'payment_method' => $request->payment_method,
                    'bank_account_id' => $request->input('bank_account_id'),
                    'payment_date' => $request->payment_date,
                    'notes' => 'Payment towards Opening Balance',
                ]);
                app(BulkPaymentAccountingService::class)->recordSupplierPayment($payment, $request->user()->id ?? null);
            } elseif ($alloc['type'] === 'purchase' && ! empty($alloc['id'])) {
                $purchase = Purchase::where('id', $alloc['id'])->where('supplier_id', $supplier->id)->first();
                if ($purchase) {
                    $payment = Payment::create([
                        'supplier_id' => $supplier->id,
                        'purchase_id' => $purchase->id,
                        'amount' => $amount,
                        'payment_method' => $request->payment_method,
                        'bank_account_id' => $request->input('bank_account_id'),
                        'payment_date' => $request->payment_date,
                        'notes' => 'Bulk Payment Allocation',
                    ]);
                    app(BulkPaymentAccountingService::class)->recordSupplierPayment($payment, $request->user()->id ?? null);

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

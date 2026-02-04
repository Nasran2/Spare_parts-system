<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;

class PaymentController extends Controller
{
    public function create(Request $request)
    {
        $purchase = Purchase::findOrFail($request->purchase_id);
        $supplier = $purchase->supplier;
        return view('payments.create', compact('purchase', 'supplier'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_date' => 'required|date',
        ]);
        $purchase = Purchase::findOrFail($request->purchase_id);
        $payment = Payment::create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $purchase->supplier_id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_date' => $request->payment_date,
            'notes' => $request->notes,
        ]);
        // Update paid and due amounts
        $purchase->paid_amount += $payment->amount;
        $purchase->due_amount = max(0, $purchase->total_amount - $purchase->paid_amount);
        $purchase->payment_status = $purchase->due_amount > 0 ? 'partial' : 'paid';
        $purchase->save();
        return redirect()->route('suppliers.show', $purchase->supplier_id)->with('success', 'Payment added successfully!');
    }
}

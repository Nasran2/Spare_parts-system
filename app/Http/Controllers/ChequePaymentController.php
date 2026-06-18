<?php

namespace App\Http\Controllers;

use App\Models\ChequePayment;
use App\Services\ChequePaymentService;
use Illuminate\Http\Request;

class ChequePaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = ChequePayment::with(['sale.customer', 'customer', 'user', 'processor'])->latest('cheque_date')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('cheque_date', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('cheque_date', '<=', $request->date('date_to'));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('cheque_number', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%")
                    ->orWhere('account_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('sale', fn ($sale) => $sale->where('sale_no', 'like', "%{$search}%"));
            });
        }

        $cheques = $query->paginate(20)->appends($request->query());
        $currency = config('app.currency', 'Rs ');

        return view('cheque-payments.index', compact('cheques', 'currency'));
    }

    public function pass(Request $request, ChequePayment $chequePayment, ChequePaymentService $service)
    {
        $service->pass($chequePayment, $request->user()?->id);

        return back()->with('success', 'Cheque marked as passed and added to paid/accounting.');
    }

    public function return(Request $request, ChequePayment $chequePayment, ChequePaymentService $service)
    {
        $service->return($chequePayment, $request->user()?->id);

        return back()->with('success', 'Cheque returned. The amount is now due from the customer.');
    }
}

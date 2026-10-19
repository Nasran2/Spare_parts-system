<?php

namespace App\Http\Controllers;

use App\Exports\ChequePaymentsExport;
use App\Models\ChequePayment;
use App\Services\ChequePaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ChequePaymentController extends Controller
{
    public function index(Request $request)
    {
        $cheques = $this->filteredQuery($request)->paginate(20)->appends($request->query());
        $currency = config('app.currency', 'Rs ');

        $bankAccounts = \App\Models\Accounting\BankAccount::where('is_active', true)->whereHas('chartAccount', fn ($query) => $query->where('is_active', true))->orderBy('bank_name')->get();
        $suppliers = \App\Models\Supplier::where('is_active', true)->get();

        return view('cheque-payments.index', compact('cheques', 'currency', 'bankAccounts', 'suppliers'));
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = ChequePayment::with(['sale.customer', 'customer', 'supplier', 'purchase', 'preOrder', 'user', 'processor', 'bankAccount'])
            ->latest('cheque_date')->latest('id');

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

        return $query;
    }

    private function exportRows(Request $request): Collection
    {
        return $this->filteredQuery($request)->get()->map(function (ChequePayment $cheque) {
            $ownCheque = $cheque->type === 'own';

            return [
                'date' => $cheque->cheque_date?->format('Y-m-d') ?? '-',
                'cheque_number' => $cheque->cheque_number,
                'bank' => $cheque->bank_name ?: '-',
                'account_name' => $cheque->account_name ?: '-',
                'type' => $ownCheque ? 'Own Cheque' : 'Customer Cheque',
                'party' => $ownCheque ? ($cheque->supplier?->name ?? 'Unknown Supplier') : ($cheque->customer?->name ?? $cheque->sale?->customer?->name ?? 'Walk-in Customer'),
                'reference' => $ownCheque
                    ? ($cheque->purchase?->purchase_no ?? ($cheque->purchase_id ? 'PUR-'.$cheque->purchase_id : '-'))
                    : ($cheque->sale?->sale_no ?? $cheque->preOrder?->pre_order_number ?? 'Advance / Opening Balance'),
                'amount' => (float) $cheque->amount,
                'status' => ucfirst($cheque->status),
                'endorsed_to' => ! $ownCheque ? ($cheque->supplier?->name ?? '-') : '-',
                'processed_at' => $cheque->processed_at?->format('Y-m-d H:i') ?? '-',
                'auto_passed' => $cheque->auto_passed ? 'Yes' : 'No',
                'notes' => $cheque->notes ?: '-',
            ];
        });
    }

    public function exportPdf(Request $request)
    {
        $rows = $this->exportRows($request);
        $currency = trim(config('app.currency', 'Rs'));
        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);

        return Pdf::loadView('cheque-payments.pdf', compact('rows', 'currency', 'filters'))
            ->setPaper('a4', 'landscape')
            ->download('cheque-payments-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new ChequePaymentsExport($this->exportRows($request)), 'cheque-payments-'.now()->format('Y-m-d').'.xlsx');
    }

    public function pass(Request $request, ChequePayment $chequePayment, ChequePaymentService $service)
    {
        $validated = $request->validate([
            'passed_bank_id' => [\Illuminate\Validation\Rule::requiredIf($chequePayment->status === 'pending' && ! $request->filled('supplier_id')), 'nullable', 'integer', \Illuminate\Validation\Rule::exists('bank_accounts', 'id')->where('is_active', true)],
            'supplier_id' => 'nullable|exists:suppliers,id',
        ], ['passed_bank_id.required' => 'Select the bank account where this cheque is being cleared.']);

        $service->pass($chequePayment, $request->user()?->id, false, $validated['passed_bank_id'] ?? null, $validated['supplier_id'] ?? null);

        return back()->with('success', 'Cheque marked as passed and added to paid/accounting.');
    }

    public function return(Request $request, ChequePayment $chequePayment, ChequePaymentService $service)
    {
        $service->return($chequePayment, $request->user()?->id);

        return back()->with('success', 'Cheque returned. The amount is now due from the customer.');
    }
}

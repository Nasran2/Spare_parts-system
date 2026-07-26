<?php

namespace App\Http\Controllers;

use App\Exports\TaxReportExport;
use App\Models\Accounting\AccountTransaction;
use App\Models\Accounting\ChartAccount;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\TaxAdjustment;
use App\Models\TaxLedgerEntry;
use App\Models\TaxPayment;
use App\Models\TaxSetting;
use App\Models\TransactionTaxLine;
use App\Models\User;
use App\Services\DecimalMath;
use App\Services\TaxInvoiceNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class TaxManagementController extends Controller
{
    public const REPORTS = [
        'output-vat' => 'Output VAT Report',
        'input-vat' => 'Input VAT Report',
        'vat-summary' => 'VAT Summary Report',
        'vat-payable' => 'VAT Payable Report',
        'vat-payment' => 'VAT Payment Report',
        'vat-adjustment' => 'VAT Adjustment Report',
        'taxable-sales' => 'Taxable Sales Report',
        'taxable-purchases' => 'Taxable Purchase Report',
        'zero-rated' => 'Zero-Rated Supply Report',
        'exempt' => 'Exempt Supply Report',
        'customer-invoice-register' => 'Customer VAT Invoice Register',
        'supplier-invoice-register' => 'Supplier VAT Invoice Register',
        'sales-return' => 'Sales Return VAT Report',
        'purchase-return' => 'Purchase Return VAT Report',
    ];

    public function dashboard(Request $request)
    {
        [$from, $to] = $this->dates($request);
        $entries = TaxLedgerEntry::query()
            ->where('status', 'posted')
            ->whereBetween('entry_date', [$from, $to]);
        $output = DecimalMath::parse((string) (clone $entries)->where('direction', 'output')->sum('tax_amount'));
        $input = DecimalMath::parse((string) (clone $entries)->where('direction', 'input')->sum('tax_amount'));
        $todayEntries = TaxLedgerEntry::query()->where('status', 'posted')->whereDate('entry_date', today());
        $payments = DecimalMath::parse((string) TaxPayment::query()
            ->where('status', 'finalized')
            ->whereBetween('payment_date', [$from, $to])
            ->sum('paid_amount'));
        $debitAdjustments = DecimalMath::parse((string) TaxAdjustment::query()
            ->where('status', 'approved')
            ->where('adjustment_type', 'debit')
            ->whereBetween('adjustment_date', [$from, $to])
            ->sum('amount'));
        $creditAdjustments = DecimalMath::parse((string) TaxAdjustment::query()
            ->where('status', 'approved')
            ->where('adjustment_type', 'credit')
            ->whereBetween('adjustment_date', [$from, $to])
            ->sum('amount'));
        $previousBalance = $this->balanceBefore($from);
        $payable = $output - $input + $debitAdjustments - $creditAdjustments - $payments + $previousBalance;

        $stats = [
            'today_taxable_sales' => (clone $todayEntries)->where('direction', 'output')->sum('taxable_amount'),
            'today_output_vat' => (clone $todayEntries)->where('direction', 'output')->sum('tax_amount'),
            'today_taxable_purchases' => (clone $todayEntries)->where('direction', 'input')->sum('taxable_amount'),
            'today_input_vat' => (clone $todayEntries)->where('direction', 'input')->sum('tax_amount'),
            'output_vat' => DecimalMath::currency($output),
            'input_vat' => DecimalMath::currency($input),
            'payments' => DecimalMath::currency($payments),
            'debit_adjustments' => DecimalMath::currency($debitAdjustments),
            'credit_adjustments' => DecimalMath::currency($creditAdjustments),
            'previous_balance' => DecimalMath::currency($previousBalance),
            'payable' => DecimalMath::currency($payable),
        ];
        $recent = TaxLedgerEntry::query()
            ->where('status', 'posted')
            ->whereBetween('entry_date', [$from, $to])
            ->latest('entry_date')
            ->limit(15)
            ->get();

        return view('tax.dashboard', compact('stats', 'recent', 'from', 'to'));
    }

    public function ledger(Request $request, string $direction)
    {
        abort_unless(in_array($direction, ['output', 'input'], true), 404);
        [$from, $to] = $this->dates($request);
        $entries = TaxLedgerEntry::query()
            ->where('direction', $direction)
            ->whereBetween('entry_date', [$from, $to])
            ->when($request->filled('invoice'), fn (Builder $query) => $query->where('invoice_number', 'like', '%'.$request->string('invoice').'%'))
            ->latest('entry_date')
            ->paginate((int) Setting::get('items_per_page', 10))
            ->withQueryString();

        return view('tax.ledger', compact('entries', 'direction', 'from', 'to'));
    }

    public function dashboardDetails(Request $request, string $metric)
    {
        abort_unless(in_array($metric, ['taxable_sales', 'output_vat', 'taxable_purchases', 'input_vat', 'payments', 'outstanding'], true), 404);
        [$from, $to] = $this->dates($request);
        if ($request->boolean('today')) {
            $from = $to = now()->toDateString();
        }

        if ($metric === 'payments') {
            return response()->json(TaxPayment::query()
                ->where('status', 'finalized')
                ->whereBetween('payment_date', [$from, $to])
                ->latest('payment_date')
                ->get()
                ->map(fn (TaxPayment $payment) => [
                    'invoice_number' => $payment->reference,
                    'date' => $payment->payment_date?->toDateString(),
                    'party' => 'Inland Revenue VAT Payment',
                    'tin' => '',
                    'taxable_value' => $payment->payable_amount,
                    'vat_rate' => '',
                    'vat_amount' => $payment->paid_amount,
                    'total' => $payment->paid_amount,
                    'store' => '',
                    'user' => $payment->created_by ? User::find($payment->created_by)?->name : '',
                    'status' => $payment->status,
                    'url' => route('tax.payments'),
                ]));
        }

        $entries = TaxLedgerEntry::query()
            ->where('status', 'posted')
            ->whereBetween('entry_date', [$from, $to])
            ->when(in_array($metric, ['taxable_sales', 'output_vat'], true), fn ($q) => $q->where('direction', 'output'))
            ->when(in_array($metric, ['taxable_purchases', 'input_vat'], true), fn ($q) => $q->where('direction', 'input'))
            ->latest('entry_date')
            ->limit(250)
            ->get();

        return response()->json($entries->map(function (TaxLedgerEntry $entry) {
            $sale = null;
            $purchase = null;
            if ($entry->transaction_type === 'sale') {
                $sale = Sale::with('customer')->find($entry->transaction_id);
            } elseif ($entry->transaction_type === 'sale_return') {
                $sale = SaleReturn::with('sale.customer')->find($entry->transaction_id)?->sale;
            } elseif ($entry->transaction_type === 'purchase') {
                $purchase = Purchase::with('supplier')->find($entry->transaction_id);
            } elseif ($entry->transaction_type === 'purchase_return') {
                $purchase = PurchaseReturn::with('purchase.supplier')->find($entry->transaction_id)?->purchase;
            }
            $lines = TransactionTaxLine::query()
                ->where('transaction_type', $entry->transaction_type)
                ->where('transaction_id', $entry->transaction_id)
                ->get();
            $total = 0;
            foreach ($lines as $line) {
                $total += DecimalMath::parse($line->total_amount);
            }

            return [
                'invoice_number' => $entry->invoice_number,
                'date' => $entry->entry_date?->toDateString(),
                'party' => $sale?->customer?->name ?? $purchase?->supplier?->name ?? '',
                'tin' => $sale?->customer?->tin ?? $purchase?->supplier?->tin ?? '',
                'taxable_value' => $entry->taxable_amount,
                'vat_rate' => $lines->pluck('tax_rate')->unique()->implode(', '),
                'vat_amount' => $entry->tax_amount,
                'total' => DecimalMath::currency($total),
                'store' => $entry->store_id ? Store::find($entry->store_id)?->name : '',
                'user' => $entry->created_by ? User::find($entry->created_by)?->name : '',
                'status' => $entry->status,
                'url' => $sale
                    ? route('sales.show', $sale)
                    : ($purchase ? route('purchases.show', $purchase) : null),
            ];
        }));
    }

    public function payments(Request $request)
    {
        $payments = TaxPayment::query()->latest('payment_date')->paginate(15);
        $accounts = ChartAccount::query()
            ->where('is_active', true)
            ->where('type', 'asset')
            ->orderBy('code')
            ->get();

        return view('tax.payments', compact('payments', 'accounts'));
    }

    public function storePayment(Request $request)
    {
        $data = $request->validate([
            'tax_period' => ['required', 'date_format:Y-m'],
            'payment_date' => ['required', 'date'],
            'reference' => ['required', 'string', 'max:100', 'unique:tax_payments,reference'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_deposit', 'bank_transfer', 'card', 'mobile_payment', 'cheque'])],
            'account_id' => ['required', 'exists:chart_accounts,id'],
            'paid_amount' => ['required', 'decimal:0,4', 'min:0.0001'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'finalize' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($request, $data) {
            $balance = $this->periodBalance($data['tax_period']);
            $paid = DecimalMath::parse($data['paid_amount']);
            $attachment = $request->hasFile('attachment')
                ? $request->file('attachment')->store('tax-payments', 'public')
                : null;
            $payment = TaxPayment::create([
                ...$balance,
                'tax_period' => $data['tax_period'],
                'payment_date' => $data['payment_date'],
                'reference' => trim($data['reference']),
                'payment_method' => $data['payment_method'],
                'account_id' => $data['account_id'],
                'paid_amount' => DecimalMath::format($paid),
                'remaining_amount' => DecimalMath::format(
                    DecimalMath::parse($balance['payable_amount']) - $paid
                ),
                'notes' => $data['notes'] ?? null,
                'attachment' => $attachment,
                'status' => $request->boolean('finalize') ? 'finalized' : 'draft',
                'created_by' => $request->user()?->id,
                'finalized_by' => $request->boolean('finalize') ? $request->user()?->id : null,
                'finalized_at' => $request->boolean('finalize') ? now() : null,
            ]);

            if ($payment->status === 'finalized') {
                $this->postPaymentAccounting($payment);
            }
            ActivityLog::log('create', "Created VAT payment {$payment->reference}", $payment, ['new' => $payment->toArray()]);

            return back()->with('success', 'VAT payment saved.');
        });
    }

    public function finalizePayment(Request $request, TaxPayment $taxPayment)
    {
        if ($taxPayment->status !== 'draft') {
            throw ValidationException::withMessages(['payment' => 'Only draft payments can be finalized.']);
        }

        DB::transaction(function () use ($request, $taxPayment) {
            $balance = $this->periodBalance($taxPayment->tax_period);
            $taxPayment->update([
                ...$balance,
                'remaining_amount' => DecimalMath::format(
                    DecimalMath::parse($balance['payable_amount']) - DecimalMath::parse($taxPayment->paid_amount)
                ),
                'status' => 'finalized',
                'finalized_by' => $request->user()?->id,
                'finalized_at' => now(),
            ]);
            $this->postPaymentAccounting($taxPayment);
            ActivityLog::log('finalize', "Finalized VAT payment {$taxPayment->reference}", $taxPayment);
        });

        return back()->with('success', 'VAT payment finalized.');
    }

    public function updatePayment(Request $request, TaxPayment $taxPayment)
    {
        if ($taxPayment->status !== 'draft') {
            throw ValidationException::withMessages(['payment' => 'Only draft payments may be edited.']);
        }
        $data = $request->validate([
            'tax_period' => ['required', 'date_format:Y-m'],
            'payment_date' => ['required', 'date'],
            'reference' => ['required', 'string', 'max:100', Rule::unique('tax_payments', 'reference')->ignore($taxPayment)],
            'payment_method' => ['required', Rule::in(['cash', 'bank_deposit', 'bank_transfer', 'card', 'mobile_payment', 'cheque'])],
            'account_id' => ['required', 'exists:chart_accounts,id'],
            'paid_amount' => ['required', 'decimal:0,4', 'min:0.0001'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        DB::transaction(function () use ($request, $taxPayment, $data) {
            $old = $taxPayment->toArray();
            $balance = $this->periodBalance($data['tax_period']);
            $paid = DecimalMath::parse($data['paid_amount']);
            $taxPayment->update([
                ...$balance,
                ...collect($data)->except('attachment')->all(),
                'remaining_amount' => DecimalMath::format(DecimalMath::parse($balance['payable_amount']) - $paid),
                'attachment' => $request->hasFile('attachment')
                    ? $request->file('attachment')->store('tax-payments', 'public')
                    : $taxPayment->attachment,
            ]);
            ActivityLog::log('update', "Updated draft VAT payment {$taxPayment->reference}", $taxPayment, [
                'old' => $old,
                'new' => $taxPayment->fresh()->toArray(),
            ]);
        });

        return back()->with('success', 'Draft VAT payment updated.');
    }

    public function reversePayment(Request $request, TaxPayment $taxPayment)
    {
        if ($taxPayment->status !== 'finalized') {
            throw ValidationException::withMessages(['payment' => 'Only finalized payments can be reversed.']);
        }

        DB::transaction(function () use ($request, $taxPayment) {
            $account = $taxPayment->account_id ? ChartAccount::find($taxPayment->account_id) : null;
            AccountTransaction::create([
                'account_id' => $account?->id ?? $this->outputVatAccount()->id,
                'related_account_id' => $this->outputVatAccount()->id,
                'user_id' => $request->user()?->id,
                'transaction_date' => now()->toDateString(),
                'direction' => 'in',
                'payment_method' => $this->accountPaymentMethod($taxPayment->payment_method),
                'amount' => $taxPayment->paid_amount,
                'reference_no' => $taxPayment->reference,
                'source_type' => 'tax_payment_reversal',
                'source_id' => $taxPayment->id,
                'description' => 'Reversal of VAT payment '.$taxPayment->reference,
            ]);
            $account?->increment('current_balance', $taxPayment->paid_amount);
            $this->outputVatAccount()->increment('current_balance', $taxPayment->paid_amount);
            $taxPayment->update(['status' => 'reversed']);
            ActivityLog::log('reverse', "Reversed VAT payment {$taxPayment->reference}", $taxPayment);
        });

        return back()->with('success', 'VAT payment reversed with a retained audit trail.');
    }

    public function destroyPayment(TaxPayment $taxPayment)
    {
        if ($taxPayment->status !== 'draft') {
            throw ValidationException::withMessages(['payment' => 'Only draft payments may be removed.']);
        }
        $taxPayment->delete();
        ActivityLog::log('delete', "Removed draft VAT payment {$taxPayment->reference}", $taxPayment);

        return back()->with('success', 'Draft VAT payment moved to history.');
    }

    public function adjustments()
    {
        $adjustments = TaxAdjustment::query()->latest('adjustment_date')->paginate(15);

        return view('tax.adjustments', compact('adjustments'));
    }

    public function storeAdjustment(Request $request)
    {
        $data = $request->validate([
            'tax_period' => ['required', 'date_format:Y-m'],
            'adjustment_date' => ['required', 'date'],
            'adjustment_type' => ['required', Rule::in(['debit', 'credit'])],
            'amount' => ['required', 'decimal:0,4', 'min:0.0001'],
            'reason' => ['required', 'string', 'max:2000'],
            'reference' => ['required', 'string', 'max:100', 'unique:tax_adjustments,reference'],
            'approve' => ['nullable', 'boolean'],
        ]);
        $adjustment = TaxAdjustment::create([
            ...$data,
            'status' => $request->boolean('approve') ? 'approved' : 'draft',
            'created_by' => $request->user()?->id,
            'approved_by' => $request->boolean('approve') ? $request->user()?->id : null,
            'approved_at' => $request->boolean('approve') ? now() : null,
        ]);
        ActivityLog::log('create', "Created VAT adjustment {$adjustment->reference}", $adjustment);

        return back()->with('success', 'VAT adjustment saved.');
    }

    public function approveAdjustment(Request $request, TaxAdjustment $taxAdjustment)
    {
        if ($taxAdjustment->status !== 'draft') {
            throw ValidationException::withMessages(['adjustment' => 'Only draft adjustments may be approved.']);
        }
        $taxAdjustment->update([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);
        ActivityLog::log('approve', "Approved VAT adjustment {$taxAdjustment->reference}", $taxAdjustment);

        return back()->with('success', 'VAT adjustment approved.');
    }

    public function reports(Request $request)
    {
        $type = $request->input('type', 'vat-summary');
        abort_unless(isset(self::REPORTS[$type]), 404);
        [$from, $to] = $this->dates($request);
        $rows = $this->reportRows($type, $from, $to, $request);
        $totals = $this->reportTotals($rows);

        return view('tax.reports', [
            'reports' => self::REPORTS,
            'type' => $type,
            'title' => self::REPORTS[$type],
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'tin']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'tin']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function exportReport(Request $request, string $format)
    {
        $type = $request->input('type', 'vat-summary');
        abort_unless(isset(self::REPORTS[$type]) && in_array($format, ['pdf', 'xlsx'], true), 404);
        [$from, $to] = $this->dates($request);
        $rows = $this->reportRows($type, $from, $to, $request);
        $totals = $this->reportTotals($rows);
        $headings = ['Invoice', 'Date', 'Direction', 'Taxable Value', 'VAT', 'Status', 'Store', 'User'];
        $flat = $rows->map(fn ($row) => [
            $row->invoice_number,
            optional($row->entry_date)->toDateString(),
            ucfirst($row->direction),
            $row->taxable_amount,
            $row->tax_amount,
            ucfirst($row->status),
            $row->store_id,
            $row->created_by,
        ]);
        $flat->push([
            'TOTAL',
            '',
            '',
            $totals['taxable'],
            $totals['vat'],
            '',
            '',
            '',
        ]);
        $filename = $type.'-'.$from.'-'.$to;
        if ($format === 'xlsx') {
            return Excel::download(new TaxReportExport($flat, $headings), $filename.'.xlsx');
        }

        return Pdf::loadView('tax.pdf.report', [
            'title' => self::REPORTS[$type],
            'headings' => $headings,
            'rows' => $flat,
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'landscape')->download($filename.'.pdf');
    }

    public function taxInvoice(Sale $sale, TaxInvoiceNumberService $numbers)
    {
        $sale->loadMissing(['items.product', 'customer', 'user', 'store', 'payments', 'taxLines']);
        $settings = TaxSetting::current($sale->sale_date);
        $numbers->issue($sale);
        $sale->refresh()->load(['items.product', 'customer', 'user', 'store', 'payments', 'taxLines']);
        $lineById = $sale->taxLines->keyBy('transaction_line_id');
        $rows = $sale->items->map(fn ($item) => [
            'reference' => $item->product?->sku ?: (string) $item->product_id,
            'description' => $item->product?->name ?: 'Item',
            'quantity' => $item->quantity,
            'unit_price' => $lineById->get($item->id)?->original_unit_price ?? $item->unit_price,
            'taxable_amount' => $lineById->get($item->id)?->taxable_amount ?? 0,
        ]);
        $taxableMinor = 0;
        $vatMinor = 0;
        $totalMinor = 0;
        foreach ($sale->taxLines as $line) {
            $taxableMinor += DecimalMath::parse($line->taxable_amount);
            $vatMinor += DecimalMath::parse($line->vat_amount);
            $totalMinor += DecimalMath::parse($line->total_amount);
        }
        $taxable = DecimalMath::currency($taxableMinor);
        $vat = DecimalMath::currency($vatMinor);
        $total = DecimalMath::currency($totalMinor);
        $shop = [
            'name' => Setting::get('shop_name', config('app.name')),
            'address' => Setting::get('shop_address', ''),
            'phone' => Setting::get('shop_phone', ''),
        ];

        $pdf = Pdf::loadView('tax.pdf.invoice', [
            'sale' => $sale,
            'settings' => $sale->tax_snapshot ?: $settings->snapshot(),
            'shop' => $shop,
            'rows' => $rows,
            'taxable' => $taxable,
            'vat' => $vat,
            'total' => $total,
            'amountWords' => $this->amountInWords((string) $total),
        ])->setOption(['isRemoteEnabled' => true])->setPaper('a4', 'portrait');

        return request()->boolean('download')
            ? $pdf->download($sale->tax_invoice_number.'.pdf')
            : $pdf->stream($sale->tax_invoice_number.'.pdf');
    }

    public function emailTaxInvoice(Request $request, Sale $sale, TaxInvoiceNumberService $numbers)
    {
        $email = $request->validate(['email' => ['required', 'email']])['email'];
        $response = $this->taxInvoice($sale, $numbers);
        $pdf = $response->getContent();
        Mail::raw('Please find your Sri Lankan VAT Tax Invoice attached.', function ($message) use ($email, $sale, $pdf) {
            $message->to($email)
                ->subject('Tax Invoice '.$sale->tax_invoice_number)
                ->attachData($pdf, $sale->tax_invoice_number.'.pdf', ['mime' => 'application/pdf']);
        });
        ActivityLog::log('email', "Emailed Tax Invoice {$sale->tax_invoice_number} to {$email}", $sale);

        return back()->with('success', 'Tax Invoice emailed successfully.');
    }

    private function dates(Request $request): array
    {
        $range = (string) $request->input('range', 'custom');
        $now = now();
        [$presetFrom, $presetTo] = match ($range) {
            'today' => [$now->copy(), $now->copy()],
            'yesterday' => [$now->copy()->subDay(), $now->copy()->subDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'last_quarter' => [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [null, null],
        };
        $from = $presetFrom?->toDateString()
            ?? $request->date('from')?->toDateString()
            ?? $now->copy()->startOfMonth()->toDateString();
        $to = $presetTo?->toDateString()
            ?? $request->date('to')?->toDateString()
            ?? $now->copy()->endOfMonth()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private function balanceBefore(string $date): int
    {
        $entries = TaxLedgerEntry::query()->where('status', 'posted')->whereDate('entry_date', '<', $date);
        $output = DecimalMath::parse((string) (clone $entries)->where('direction', 'output')->sum('tax_amount'));
        $input = DecimalMath::parse((string) (clone $entries)->where('direction', 'input')->sum('tax_amount'));
        $debit = DecimalMath::parse((string) TaxAdjustment::query()->where('status', 'approved')->where('adjustment_type', 'debit')->whereDate('adjustment_date', '<', $date)->sum('amount'));
        $credit = DecimalMath::parse((string) TaxAdjustment::query()->where('status', 'approved')->where('adjustment_type', 'credit')->whereDate('adjustment_date', '<', $date)->sum('amount'));
        $payments = DecimalMath::parse((string) TaxPayment::query()->where('status', 'finalized')->whereDate('payment_date', '<', $date)->sum('paid_amount'));

        return $output - $input + $debit - $credit - $payments;
    }

    private function periodBalance(string $period): array
    {
        $entries = TaxLedgerEntry::query()->where('status', 'posted')->where('tax_period', $period);
        $output = DecimalMath::parse((string) (clone $entries)->where('direction', 'output')->sum('tax_amount'));
        $input = DecimalMath::parse((string) (clone $entries)->where('direction', 'input')->sum('tax_amount'));
        $debit = DecimalMath::parse((string) TaxAdjustment::query()->where('status', 'approved')->where('tax_period', $period)->where('adjustment_type', 'debit')->sum('amount'));
        $credit = DecimalMath::parse((string) TaxAdjustment::query()->where('status', 'approved')->where('tax_period', $period)->where('adjustment_type', 'credit')->sum('amount'));
        $paid = DecimalMath::parse((string) TaxPayment::query()
            ->where('status', 'finalized')
            ->where('tax_period', $period)
            ->sum('paid_amount'));
        $previous = $this->balanceBefore(Carbon::createFromFormat('Y-m', $period)->startOfMonth()->toDateString());
        $payable = $output - $input + $debit - $credit - $paid + $previous;

        return [
            'output_vat' => DecimalMath::format($output),
            'input_vat' => DecimalMath::format($input),
            'adjustments' => DecimalMath::format($debit - $credit),
            'previous_balance' => DecimalMath::format($previous),
            'payable_amount' => DecimalMath::format($payable),
        ];
    }

    private function reportRows(string $type, string $from, string $to, Request $request)
    {
        if ($type === 'vat-payment') {
            return TaxPayment::query()
                ->whereBetween('payment_date', [$from, $to])
                ->when($request->filled('invoice'), fn ($q) => $q->where('reference', 'like', '%'.$request->string('invoice').'%'))
                ->when($request->filled('user_id'), fn ($q) => $q->where('created_by', $request->integer('user_id')))
                ->orderBy('payment_date')
                ->get()
                ->map(fn (TaxPayment $payment) => (object) [
                    'invoice_number' => $payment->reference,
                    'entry_date' => $payment->payment_date,
                    'direction' => 'payment',
                    'taxable_amount' => $payment->payable_amount,
                    'tax_amount' => $payment->paid_amount,
                    'status' => $payment->status,
                    'store_id' => null,
                    'created_by' => $payment->created_by,
                ]);
        }
        if ($type === 'vat-adjustment') {
            return TaxAdjustment::query()
                ->whereBetween('adjustment_date', [$from, $to])
                ->when($request->filled('invoice'), fn ($q) => $q->where('reference', 'like', '%'.$request->string('invoice').'%'))
                ->when($request->filled('user_id'), fn ($q) => $q->where('created_by', $request->integer('user_id')))
                ->orderBy('adjustment_date')
                ->get()
                ->map(fn (TaxAdjustment $adjustment) => (object) [
                    'invoice_number' => $adjustment->reference,
                    'entry_date' => $adjustment->adjustment_date,
                    'direction' => $adjustment->adjustment_type,
                    'taxable_amount' => 0,
                    'tax_amount' => $adjustment->adjustment_type === 'credit'
                        ? '-'.$adjustment->amount
                        : $adjustment->amount,
                    'status' => $adjustment->status,
                    'store_id' => null,
                    'created_by' => $adjustment->created_by,
                ]);
        }
        if (in_array($type, ['zero-rated', 'exempt', 'taxable-sales', 'taxable-purchases'], true)) {
            return $this->lineReportRows($type, $from, $to, $request);
        }

        $query = TaxLedgerEntry::query()->whereBetween('entry_date', [$from, $to]);
        if (in_array($type, ['output-vat', 'taxable-sales', 'zero-rated', 'exempt', 'customer-invoice-register'], true)) {
            $query->where('direction', 'output');
        }
        if (in_array($type, ['input-vat', 'taxable-purchases', 'supplier-invoice-register'], true)) {
            $query->where('direction', 'input');
        }
        if ($type === 'sales-return') {
            $query->where('transaction_type', 'like', 'sale_return%');
        }
        if ($type === 'purchase-return') {
            $query->where('transaction_type', 'like', 'purchase_return%');
        }
        if ($request->filled('customer_id') || $request->filled('supplier_id') || $request->filled('tin')) {
            $sales = ($request->filled('customer_id') || $request->filled('tin'))
                ? Sale::query()
                    ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
                    ->when($request->filled('tin'), fn ($q) => $q->whereHas('customer', fn ($customer) => $customer->where('tin', 'like', '%'.$request->string('tin').'%')))
                    ->pluck('id')
                : collect();
            $purchases = ($request->filled('supplier_id') || $request->filled('tin'))
                ? Purchase::query()
                    ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
                    ->when($request->filled('tin'), fn ($q) => $q->whereHas('supplier', fn ($supplier) => $supplier->where('tin', 'like', '%'.$request->string('tin').'%')))
                    ->pluck('id')
                : collect();
            $saleReturns = SaleReturn::query()->whereIn('sale_id', $sales)->pluck('id');
            $purchaseReturns = PurchaseReturn::query()->whereIn('purchase_id', $purchases)->pluck('id');
            $query->where(function ($q) use ($sales, $purchases, $saleReturns, $purchaseReturns) {
                $q->where(fn ($sale) => $sale->where('transaction_type', 'sale')->whereIn('transaction_id', $sales))
                    ->orWhere(fn ($return) => $return->where('transaction_type', 'sale_return')->whereIn('transaction_id', $saleReturns))
                    ->orWhere(fn ($purchase) => $purchase->where('transaction_type', 'purchase')->whereIn('transaction_id', $purchases))
                    ->orWhere(fn ($return) => $return->where('transaction_type', 'purchase_return')->whereIn('transaction_id', $purchaseReturns));
            });
        }
        if ($request->filled('payment_status')) {
            $sales = Sale::query()->where('payment_status', $request->string('payment_status'))->pluck('id');
            $purchases = Purchase::query()->where('payment_status', $request->string('payment_status'))->pluck('id');
            $query->where(function ($q) use ($sales, $purchases) {
                $q->where(fn ($sale) => $sale->where('transaction_type', 'sale')->whereIn('transaction_id', $sales))
                    ->orWhere(fn ($purchase) => $purchase->where('transaction_type', 'purchase')->whereIn('transaction_id', $purchases));
            });
        }
        if ($request->filled('tax_status') || $request->filled('vat_rate') || $request->filled('product_id') || $request->filled('category_id')) {
            $lineQuery = TransactionTaxLine::query();
            $status = $type === 'zero-rated' ? 'zero_rated' : ($type === 'exempt' ? 'exempt' : null);
            if ($status) {
                $lineQuery->where('tax_status', $status);
            }
            $lineQuery
                ->when($request->filled('tax_status'), fn ($q) => $q->where('tax_status', $request->string('tax_status')))
                ->when($request->filled('vat_rate'), fn ($q) => $q->where('tax_rate', $request->input('vat_rate')));
            $productIds = null;
            if ($request->filled('category_id')) {
                $categoryId = $request->integer('category_id');
                $productIds = Product::query()
                    ->where('category_id', $categoryId)
                    ->orWhereHas('categories', fn ($q) => $q->whereKey($categoryId))
                    ->pluck('id');
            }
            if ($request->filled('product_id')) {
                $productIds = collect([$request->integer('product_id')]);
            }
            if ($productIds !== null) {
                $saleLineIds = DB::table('sale_items')->whereIn('product_id', $productIds)->pluck('id');
                $purchaseLineIds = DB::table('purchase_items')->whereIn('product_id', $productIds)->pluck('id');
                $saleReturnLineIds = DB::table('sale_return_items')->whereIn('product_id', $productIds)->pluck('id');
                $purchaseReturnLineIds = DB::table('purchase_return_items')->whereIn('product_id', $productIds)->pluck('id');
                $lineQuery->where(function ($q) use ($saleLineIds, $purchaseLineIds, $saleReturnLineIds, $purchaseReturnLineIds) {
                    $q->where(fn ($line) => $line->where('transaction_type', 'sale')->whereIn('transaction_line_id', $saleLineIds))
                        ->orWhere(fn ($line) => $line->where('transaction_type', 'purchase')->whereIn('transaction_line_id', $purchaseLineIds))
                        ->orWhere(fn ($line) => $line->where('transaction_type', 'sale_return')->whereIn('transaction_line_id', $saleReturnLineIds))
                        ->orWhere(fn ($line) => $line->where('transaction_type', 'purchase_return')->whereIn('transaction_line_id', $purchaseReturnLineIds));
                });
            }
            $pairs = $lineQuery->get()->groupBy('transaction_type');
            $query->where(function ($q) use ($pairs) {
                foreach ($pairs as $transactionType => $lines) {
                    $q->orWhere(fn ($pair) => $pair
                        ->where('transaction_type', $transactionType)
                        ->whereIn('transaction_id', $lines->pluck('transaction_id')));
                }
                if ($pairs->isEmpty()) {
                    $q->whereRaw('1 = 0');
                }
            });
        }
        if ($request->filled('invoice')) {
            $query->where('invoice_number', 'like', '%'.$request->string('invoice').'%');
        }
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->integer('store_id'));
        }
        if ($request->filled('user_id')) {
            $query->where('created_by', $request->integer('user_id'));
        }

        return $query->orderBy('entry_date')->orderBy('invoice_number')->get();
    }

    private function lineReportRows(string $type, string $from, string $to, Request $request)
    {
        $isPurchase = $type === 'taxable-purchases';
        $transactionTypes = $isPurchase ? ['purchase', 'purchase_return'] : ['sale', 'sale_return'];
        $lineQuery = TransactionTaxLine::query()
            ->whereIn('transaction_type', $transactionTypes)
            ->whereBetween('tax_period', [substr($from, 0, 7), substr($to, 0, 7)]);
        if ($type === 'zero-rated') {
            $lineQuery->where('tax_status', 'zero_rated');
        } elseif ($type === 'exempt') {
            $lineQuery->where('tax_status', 'exempt');
        } else {
            $lineQuery->whereIn('tax_status', ['standard', 'zero_rated']);
        }
        $lineQuery
            ->when($request->filled('tax_status'), fn ($q) => $q->where('tax_status', $request->string('tax_status')))
            ->when($request->filled('vat_rate'), fn ($q) => $q->where('tax_rate', $request->input('vat_rate')));

        return $lineQuery->get()->map(function (TransactionTaxLine $line) use ($request, $from, $to) {
            $ledger = TaxLedgerEntry::query()
                ->where('transaction_type', $line->transaction_type)
                ->where('transaction_id', $line->transaction_id)
                ->first();
            if (! $ledger || $ledger->entry_date->toDateString() < $from || $ledger->entry_date->toDateString() > $to) {
                return null;
            }

            $lineTable = match ($line->transaction_type) {
                'sale' => 'sale_items',
                'sale_return' => 'sale_return_items',
                'purchase' => 'purchase_items',
                'purchase_return' => 'purchase_return_items',
                default => null,
            };
            $productId = $lineTable
                ? DB::table($lineTable)->where('id', $line->transaction_line_id)->value('product_id')
                : null;
            if ($request->filled('product_id') && (int) $productId !== $request->integer('product_id')) {
                return null;
            }
            if ($request->filled('category_id') && $productId) {
                $categoryId = $request->integer('category_id');
                $matchesCategory = Product::query()
                    ->whereKey($productId)
                    ->where(fn ($q) => $q->where('category_id', $categoryId)
                        ->orWhereHas('categories', fn ($category) => $category->whereKey($categoryId)))
                    ->exists();
                if (! $matchesCategory) {
                    return null;
                }
            }

            $sale = null;
            $purchase = null;
            if ($line->transaction_type === 'sale') {
                $sale = Sale::with('customer')->find($line->transaction_id);
            } elseif ($line->transaction_type === 'sale_return') {
                $sale = SaleReturn::with('sale.customer')->find($line->transaction_id)?->sale;
            } elseif ($line->transaction_type === 'purchase') {
                $purchase = Purchase::with('supplier')->find($line->transaction_id);
            } elseif ($line->transaction_type === 'purchase_return') {
                $purchase = PurchaseReturn::with('purchase.supplier')->find($line->transaction_id)?->purchase;
            }
            if ($request->filled('customer_id') && (int) $sale?->customer_id !== $request->integer('customer_id')) {
                return null;
            }
            if ($request->filled('supplier_id') && (int) $purchase?->supplier_id !== $request->integer('supplier_id')) {
                return null;
            }
            if ($request->filled('payment_status')
                && ($sale?->payment_status ?? $purchase?->payment_status) !== (string) $request->input('payment_status')) {
                return null;
            }
            if ($request->filled('tin')) {
                $tin = (string) ($sale?->customer?->tin ?? $purchase?->supplier?->tin ?? '');
                if (! str_contains($tin, (string) $request->input('tin'))) {
                    return null;
                }
            }
            if ($request->filled('invoice') && ! str_contains((string) $ledger->invoice_number, (string) $request->input('invoice'))) {
                return null;
            }
            if ($request->filled('store_id') && (int) $ledger->store_id !== $request->integer('store_id')) {
                return null;
            }
            if ($request->filled('user_id') && (int) $ledger->created_by !== $request->integer('user_id')) {
                return null;
            }

            return (object) [
                'invoice_number' => $ledger->invoice_number,
                'entry_date' => $ledger->entry_date,
                'direction' => $ledger->direction,
                'taxable_amount' => $line->taxable_amount,
                'tax_amount' => $line->vat_amount,
                'status' => $ledger->status,
                'store_id' => $ledger->store_id,
                'created_by' => $ledger->created_by,
            ];
        })->filter()->values();
    }

    private function reportTotals($rows): array
    {
        $taxable = 0;
        $vat = 0;
        foreach ($rows as $row) {
            $taxable += DecimalMath::parse((string) $row->taxable_amount);
            $vat += DecimalMath::parse((string) $row->tax_amount);
        }

        return [
            'taxable' => DecimalMath::currency($taxable),
            'vat' => DecimalMath::currency($vat),
        ];
    }

    private function postPaymentAccounting(TaxPayment $payment): void
    {
        if (AccountTransaction::query()->where('source_type', 'tax_payment')->where('source_id', $payment->id)->exists()) {
            return;
        }
        $account = ChartAccount::findOrFail($payment->account_id);
        AccountTransaction::create([
            'account_id' => $account->id,
            'related_account_id' => $this->outputVatAccount()->id,
            'user_id' => $payment->created_by,
            'transaction_date' => $payment->payment_date,
            'direction' => 'out',
            'payment_method' => $this->accountPaymentMethod($payment->payment_method),
            'amount' => $payment->paid_amount,
            'reference_no' => $payment->reference,
            'source_type' => 'tax_payment',
            'source_id' => $payment->id,
            'description' => 'VAT payment for '.$payment->tax_period,
        ]);
        $account->decrement('current_balance', $payment->paid_amount);
        $this->outputVatAccount()->decrement('current_balance', $payment->paid_amount);
    }

    private function outputVatAccount(): ChartAccount
    {
        return ChartAccount::firstOrCreate(
            ['code' => '2100'],
            [
                'name' => 'Output VAT Payable',
                'type' => 'liability',
                'subtype' => 'output_vat',
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }

    private function accountPaymentMethod(string $method): string
    {
        return in_array($method, ['cash', 'credit', 'cheque', 'bank_deposit', 'bank_transfer', 'card', 'mobile_payment'], true)
            ? $method
            : 'cash';
    }

    private function amountInWords(string $amount): string
    {
        [$wholePart, $fractionPart] = array_pad(explode('.', $amount, 2), 2, '0');
        $whole = (int) $wholePart;
        $cents = (int) substr(str_pad($fractionPart, 2, '0'), 0, 2);
        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
            $words = ucfirst((string) $formatter->format($whole));
        } else {
            $words = number_format($whole, 0, '.', ',');
        }

        return $words.' Sri Lankan Rupees'.($cents ? ' and '.str_pad((string) $cents, 2, '0', STR_PAD_LEFT).' cents' : '').' only';
    }
}

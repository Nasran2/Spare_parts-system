<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Services\DashboardVisibilityService;
use App\Services\PrivacyModeService;
use App\Support\SecretPos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    private function hiddenProductIdsForPublic(): array
    {
        return DashboardVisibilityService::hiddenProductIdsForUser(null);
    }

    private function customerPurchaseRangesForPublic(): array
    {
        return DashboardVisibilityService::rangesForUser(null, 'hidden_customer_purchase_price_ranges');
    }

    private function customerPurchasePriceHidden(float $amount): bool
    {
        return DashboardVisibilityService::isAmountInRanges($amount, $this->customerPurchaseRangesForPublic());
    }

    private function displayInvoiceLabelForSale(Sale $sale): string
    {
        $date = $sale->sale_date ?: $sale->created_at;
        $dateColumn = $sale->sale_date ? 'sale_date' : 'created_at';

        $sales = Sale::query()
            ->where('sale_type', 'sale')
            ->where('sale_no', 'like', 'INV-%')
            ->when($date, fn ($query) => $query->whereDate($dateColumn, $date))
            ->orderByDesc('created_at')
            ->get(['id', 'sale_no', 'sale_date', 'created_at']);

        PrivacyModeService::applyDailyInvoiceLabels($sales);

        $matchedSale = $sales->firstWhere('id', $sale->id);

        return (string) ($matchedSale->privacy_display_invoice_no
            ?? PrivacyModeService::orderedInvoiceLabel((string) $sale->sale_no, 1));
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $hiddenCustomerIds = DashboardVisibilityService::hiddenCustomerIdsForUser($request->user());
        $query = Customer::orderBy('name');
        if (! empty($hiddenCustomerIds)) {
            $query->whereNotIn('id', $hiddenCustomerIds);
        }
        foreach ($this->customerPurchaseRangesForPublic() as $range) {
            if (! (bool) ($range['hide'] ?? true)) {
                continue;
            }

            $min = (float) ($range['min'] ?? 0);
            $max = (float) ($range['max'] ?? $min);
            $query->whereNotBetween('due_amount', [min($min, $max), max($min, $max)]);
        }

        $customers = $query->get();
        $controls = DashboardVisibilityService::configForUser($request->user());

        return view('customers.index', compact('customers', 'controls'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        if (view()->exists('customers.create')) {
            return view('customers.create');
        }

        return redirect()->route('customers.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric|min:0',
        ]);

        $data['phone'] = trim((string) ($data['phone'] ?? ''));

        $customer = Customer::create(array_merge($data, [
            'is_active' => $request->boolean('is_active', true),
            'opening_balance' => $request->input('opening_balance', 0),
        ]));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'customer' => $customer,
            ]);
        }

        return redirect()->route('customers.index')->with('success', 'Customer created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $customer = Customer::findOrFail($id);
        if (DashboardVisibilityService::isCustomerHiddenForUser((int) $customer->id, Auth::user())) {
            abort(404);
        }

        $start = request('start_date');
        $end = request('end_date');
        if (! $start || ! $end) {
            $year = now()->year;
            $start = $start ?: now()->setDate($year, 1, 1)->startOfDay()->toDateString();
            $end = $end ?: now()->setDate($year, 12, 31)->endOfDay()->toDateString();
        }

        $sales = $customer->sales()
            ->when($start, fn ($q) => $q->whereDate('sale_date', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('sale_date', '<=', $end))
            ->orderBy('sale_date')
            ->get()
            ->reject(fn ($s) => SecretPos::isHidden((float) $s->total_amount)
                || $this->customerPurchasePriceHidden((float) $s->total_amount)
                || DashboardVisibilityService::isSaleHiddenForUser((int) $s->id, Auth::user()));

        $controls = DashboardVisibilityService::configForUser(Auth::user());
        $customerDisplayValue = fn ($value) => DashboardVisibilityService::customerValue((float) $value, $controls);

        $periodTotals = [
            'invoice' => (float) $sales->sum('total_amount'),
            'paid' => (float) $sales->sum('paid_amount'),
        ];
        $periodTotals['balance'] = max(0, $periodTotals['invoice'] - $periodTotals['paid']);
        $periodTotals = [
            'invoice' => $customerDisplayValue($periodTotals['invoice']),
            'paid' => $customerDisplayValue($periodTotals['paid']),
            'balance' => $customerDisplayValue($periodTotals['balance']),
        ];

        $overallSales = $customer->sales
            ->reject(fn ($s) => SecretPos::isHidden((float) $s->total_amount)
                || $this->customerPurchasePriceHidden((float) $s->total_amount)
                || DashboardVisibilityService::isSaleHiddenForUser((int) $s->id, Auth::user()));
        $overallTotals = [
            'invoice' => (float) $overallSales->sum('total_amount'),
            'paid' => (float) $overallSales->sum('paid_amount'),
        ];
        $overallTotals['balance'] = max(0, $overallTotals['invoice'] - $overallTotals['paid']);
        $overallTotals = [
            'invoice' => $customerDisplayValue($overallTotals['invoice']),
            'paid' => $customerDisplayValue($overallTotals['paid']),
            'balance' => $customerDisplayValue($overallTotals['balance']),
        ];

        $isActive = PrivacyModeService::isActiveForUser(Auth::user()) && PrivacyModeService::shouldMaskForCurrentPage();
        if ($isActive) {
            PrivacyModeService::applyDailyInvoiceLabels($sales);
        }

        $transactions = [];
        foreach ($sales as $sale) {
            $invoiceLabel = $isActive ? PrivacyModeService::displayInvoiceNumber($sale) : $sale->sale_no;

            $transactions[] = [
                'date' => optional($sale->sale_date)->toDateString(),
                'reference' => $invoiceLabel,
                'invoice' => $invoiceLabel,
                'sale_id' => $sale->id,
                'sale_date' => optional($sale->sale_date)->toDateString(),
                'type' => 'Sell',
                'location' => config('app.name'),
                'payment_status' => $sale->payment_status,
                'debit' => $customerDisplayValue($sale->total_amount),
                'credit' => 0.0,
                'paid' => $customerDisplayValue($sale->paid_amount),
                'due' => $customerDisplayValue($sale->due_amount),
                'payment_method' => $sale->payment_method,
                'notes' => $sale->notes,
            ];

            if ((float) $sale->paid_amount > 0) {
                $transactions[] = [
                    'date' => optional($sale->sale_date)->toDateString(),
                    'reference' => 'PAY-'.$invoiceLabel,
                    'invoice' => $invoiceLabel,
                    'sale_id' => $sale->id,
                    'sale_date' => optional($sale->sale_date)->toDateString(),
                    'type' => 'Payment',
                    'location' => config('app.name'),
                    'payment_status' => 'paid',
                    'debit' => 0.0,
                    'credit' => $customerDisplayValue($sale->paid_amount),
                    'payment_method' => $sale->payment_method,
                    'notes' => 'Payment for '.$invoiceLabel,
                ];
            }
        }

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'customer' => $customer,
                'start_date' => $start,
                'end_date' => $end,
                'period_totals' => $periodTotals,
                'overall_totals' => $overallTotals,
                'transactions' => $transactions,
            ]);
        }

        return view('customers.show', [
            'customer' => $customer,
            'start' => $start,
            'end' => $end,
            'periodTotals' => $periodTotals,
            'overallTotals' => $overallTotals,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $customer = Customer::findOrFail($id);
        if (DashboardVisibilityService::isCustomerHiddenForUser((int) $customer->id, auth()->user())) {
            abort(404);
        }

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'customer' => $customer,
            ]);
        }

        if (view()->exists('customers.edit')) {
            return view('customers.edit', compact('customer'));
        }

        return redirect()->route('customers.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $customer = Customer::findOrFail($id);
        if (DashboardVisibilityService::isCustomerHiddenForUser((int) $customer->id, $request->user())) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $customer->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'customer' => $customer,
            ]);
        }

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $customer = Customer::findOrFail($id);
        if (DashboardVisibilityService::isCustomerHiddenForUser((int) $customer->id, auth()->user())) {
            abort(404);
        }

        if ($customer->sales()->exists()) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete customer with existing sales records',
                ], 422);
            }

            return redirect()->route('customers.index')->with('error', 'Cannot delete customer with existing sales records');
        }

        $customer->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully',
            ]);
        }

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully');
    }

    /**
     * Send payment reminder to customer.
     */
    public function sendPaymentReminder(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        if (DashboardVisibilityService::isCustomerHiddenForUser((int) $customer->id, $request->user())) {
            abort(404);
        }

        $validated = $request->validate([
            'channel' => 'required|in:sms,whatsapp',
            'message_type' => 'required|in:due_reminder,bill_link,history_link',
            'sale_id' => 'nullable|exists:sales,id',
        ]);

        if (empty($customer->phone)) {
            return back()->with('error', 'Customer phone number not available');
        }

        $message = '';

        if ($validated['message_type'] === 'due_reminder') {
            $dueAmount = $customer->due_amount;
            $message = "Dear {$customer->name},\n\n";
            $message .= "This is a friendly reminder about your outstanding balance.\n";
            $message .= 'Total Due Amount: '.config('app.currency', 'Rs ').number_format($dueAmount, 2)."\n\n";
            $message .= "Please make payment at your earliest convenience.\n";
            $message .= "Thank you for your business!\n\n";
            $message .= '- '.config('app.name', 'Vehicle POS');
        } elseif ($validated['message_type'] === 'bill_link' && ! empty($validated['sale_id'])) {
            $sale = Sale::findOrFail($validated['sale_id']);
            if (DashboardVisibilityService::isSaleHiddenForUser((int) $sale->id, $request->user())) {
                abort(404);
            }
            $billUrl = route('customer.bill.view', [$customer->id, $sale->id]);
            $displayInvoiceNo = $this->displayInvoiceLabelForSale($sale);

            $message = "Dear {$customer->name},\n\n";
            $message .= "Your invoice #{$displayInvoiceNo} is ready.\n";
            $message .= 'Amount: '.config('app.currency', 'Rs ').number_format((float) $sale->total_amount, 2)."\n";
            if ($sale->due_amount > 0) {
                $message .= 'Due: '.config('app.currency', 'Rs ').number_format((float) $sale->due_amount, 2)."\n";
            }
            $message .= "\nView/Download Bill: {$billUrl}\n\n";
            $message .= '- '.config('app.name', 'Vehicle POS');
        } elseif ($validated['message_type'] === 'history_link') {
            $historyUrl = route('customer.history.view', [$customer->id]);

            $message = "Dear {$customer->name},\n\n";
            $message .= "View your complete billing history and account statement:\n";
            $message .= "{$historyUrl}\n\n";
            $message .= '- '.config('app.name', 'Vehicle POS');
        }

        if ($validated['channel'] === 'sms') {
            $smsService = new \App\Services\SmsService;
            $sent = $smsService->send($customer->phone, $message);

            if ($sent) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment reminder sent via SMS successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send SMS. Please check your SMS configuration.',
            ], 422);
        }

        if ($validated['channel'] === 'whatsapp') {
            $whatsappService = new \App\Services\WhatsAppService;
            $whatsappLink = $whatsappService->generateLink($customer->phone, $message);

            return response()->json([
                'success' => true,
                'whatsapp_link' => $whatsappLink,
                'message' => 'WhatsApp link generated. Click to send message.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid channel selected',
        ], 422);
    }

    /**
     * Public view of customer bill (no login required).
     */
    public function viewBill($customer, $sale)
    {
        $hiddenProductIds = $this->hiddenProductIdsForPublic();
        $controls = DashboardVisibilityService::configForUser(null);

        $customer = Customer::findOrFail($customer);
        $sale = Sale::with(['items.product', 'payments', 'chequePayments'])->findOrFail($sale);

        if (DashboardVisibilityService::isCustomerHiddenForUser((int) $customer->id, null)
            || DashboardVisibilityService::isSaleHiddenForUser((int) $sale->id, null)) {
            abort(404, 'Bill not found');
        }

        if ($sale->customer_id != $customer->id) {
            abort(404, 'Bill not found');
        }

        if (SecretPos::isHidden((float) $sale->total_amount)) {
            abort(404, 'Bill not found');
        }

        if ($this->customerPurchasePriceHidden((float) $sale->total_amount)) {
            abort(404, 'Bill not found');
        }

        if (! empty($hiddenProductIds) && $sale->items->whereIn('product_id', $hiddenProductIds)->isNotEmpty()) {
            abort(404, 'Bill not found');
        }

        $businessName = \App\Models\Setting::get('business_name', config('app.name', 'Vehicle POS'));
        $businessEmail = \App\Models\Setting::get('business_email', '');
        $businessPhone = \App\Models\Setting::get('business_phone', '');
        $businessAddress = \App\Models\Setting::get('business_address', '');
        $displayInvoiceNo = $this->displayInvoiceLabelForSale($sale);

        return view('customers.public-bill', compact('customer', 'sale', 'businessName', 'businessEmail', 'businessPhone', 'businessAddress', 'controls', 'displayInvoiceNo'));
    }

    /**
     * Public view of customer billing history (no login required).
     */
    public function viewHistory($customer)
    {
        $hiddenProductIds = $this->hiddenProductIdsForPublic();
        $controls = DashboardVisibilityService::configForUser(null);

        $customer = Customer::findOrFail($customer);
        if (DashboardVisibilityService::isCustomerHiddenForUser((int) $customer->id, null)) {
            abort(404, 'Bill not found');
        }
        $sales = Sale::with('items')
            ->where('customer_id', $customer->id)
            ->orderByDesc('sale_date')
            ->orderByDesc('created_at')
            ->get()
            ->reject(function ($s) use ($hiddenProductIds) {
                if (SecretPos::isHidden((float) $s->total_amount)) {
                    return true;
                }

                if ($this->customerPurchasePriceHidden((float) $s->total_amount)) {
                    return true;
                }

                if (DashboardVisibilityService::isSaleHiddenForUser((int) $s->id, null)) {
                    return true;
                }

                if (empty($hiddenProductIds)) {
                    return false;
                }

                return $s->items->whereIn('product_id', $hiddenProductIds)->isNotEmpty();
            });

        $totalInvoice = $sales->sum('total_amount');
        $totalPaid = $sales->sum('paid_amount');
        $totalDue = $sales->sum('due_amount');
        $sales->each(function (Sale $sale) {
            $sale->privacy_display_invoice_no = $this->displayInvoiceLabelForSale($sale);
        });

        $businessName = \App\Models\Setting::get('business_name', config('app.name', 'Vehicle POS'));

        return view('customers.public-history', compact('customer', 'sales', 'totalInvoice', 'totalPaid', 'totalDue', 'businessName', 'controls'));
    }

    /**
     * Payment page for customer (no login required).
     */
    public function paymentPage($customer, $sale)
    {
        $hiddenProductIds = $this->hiddenProductIdsForPublic();
        $controls = DashboardVisibilityService::configForUser(null);

        $customer = Customer::findOrFail($customer);
        $sale = Sale::with('items')->findOrFail($sale);

        if (DashboardVisibilityService::isCustomerHiddenForUser((int) $customer->id, null)
            || DashboardVisibilityService::isSaleHiddenForUser((int) $sale->id, null)) {
            abort(404, 'Bill not found');
        }

        if ($sale->customer_id != $customer->id) {
            abort(404, 'Bill not found');
        }

        if (SecretPos::isHidden((float) $sale->total_amount)) {
            abort(404, 'Bill not found');
        }

        if ($this->customerPurchasePriceHidden((float) $sale->total_amount)) {
            abort(404, 'Bill not found');
        }

        if (! empty($hiddenProductIds) && $sale->items->whereIn('product_id', $hiddenProductIds)->isNotEmpty()) {
            abort(404, 'Bill not found');
        }

        $businessName = \App\Models\Setting::get('business_name', config('app.name', 'Vehicle POS'));
        $businessEmail = \App\Models\Setting::get('business_email', '');
        $businessPhone = \App\Models\Setting::get('business_phone', '');
        $displayInvoiceNo = $this->displayInvoiceLabelForSale($sale);

        return view('customers.public-payment', compact('customer', 'sale', 'businessName', 'businessEmail', 'businessPhone', 'controls', 'displayInvoiceNo'));
    }
}

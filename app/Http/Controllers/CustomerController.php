<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Support\SecretPos;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customers = Customer::orderBy('name')->get();
        return view('customers.index', compact('customers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // This project uses modal-based create; return a view only if it exists
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
        
        $customer = Customer::create(array_merge($data, [
            'is_active' => $request->boolean('is_active', true),
            'opening_balance' => $request->input('opening_balance', 0),
        ]));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'customer' => $customer
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

        // Date range filtering (defaults to current calendar year like the screenshot)
        $start = request('start_date');
        $end = request('end_date');
        if (!$start || !$end) {
            $year = now()->year;
            $start = $start ?: now()->setDate($year, 1, 1)->startOfDay()->toDateString();
            $end = $end ?: now()->setDate($year, 12, 31)->endOfDay()->toDateString();
        }

        // Pull sales in range
        $sales = $customer->sales()
            ->when($start, fn ($q) => $q->whereDate('sale_date', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('sale_date', '<=', $end))
            ->orderBy('sale_date')
            ->get();

        // Totals for the selected period
        $periodTotals = [
            'invoice' => (float) $sales->sum('total_amount'),
            'paid' => (float) $sales->sum('paid_amount'),
        ];
        $periodTotals['balance'] = max(0, $periodTotals['invoice'] - $periodTotals['paid']);

        // Overall totals (all time)
        $overallSales = $customer->sales;
        $overallTotals = [
            'invoice' => (float) $overallSales->sum('total_amount'),
            'paid' => (float) $overallSales->sum('paid_amount'),
        ];
        $overallTotals['balance'] = max(0, $overallTotals['invoice'] - $overallTotals['paid']);

        // Build a ledger-like transactions list. We will show a Sell row and a Payment row (if any) per sale
        $transactions = [];
        foreach ($sales as $sale) {
            $transactions[] = [
                'date' => optional($sale->sale_date)->toDateString(),
                'reference' => $sale->sale_no,
                'invoice' => $sale->sale_no,
                'sale_id' => $sale->id,
                'sale_date' => optional($sale->sale_date)->toDateString(),
                'type' => 'Sell',
                'location' => config('app.name'),
                'payment_status' => $sale->payment_status,
                'debit' => (float) $sale->total_amount,
                'credit' => 0.0,
                'paid' => (float) $sale->paid_amount,
                'due' => (float) $sale->due_amount,
                'payment_method' => $sale->payment_method,
                'notes' => $sale->notes,
            ];

            if ((float) $sale->paid_amount > 0) {
                $transactions[] = [
                    'date' => optional($sale->sale_date)->toDateString(),
                    'reference' => 'PAY-' . $sale->sale_no,
                    'invoice' => $sale->sale_no,
                    'sale_id' => $sale->id,
                    'sale_date' => optional($sale->sale_date)->toDateString(),
                    'type' => 'Payment',
                    'location' => config('app.name'),
                    'payment_status' => 'paid',
                    'debit' => 0.0,
                    'credit' => (float) $sale->paid_amount,
                    'payment_method' => $sale->payment_method,
                    'notes' => 'Payment for ' . $sale->sale_no,
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
        
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'customer' => $customer
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
                'customer' => $customer
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
        
        // Check if customer has any sales
        if ($customer->sales()->exists()) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete customer with existing sales records'
                ], 422);
            }
            return redirect()->route('customers.index')->with('error', 'Cannot delete customer with existing sales records');
        }
        
        $customer->delete();
        
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);
        }
        
        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully');
    }

    /**
     * Send payment reminder to customer
     */
    public function sendPaymentReminder(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        
        $validated = $request->validate([
            'channel' => 'required|in:sms,whatsapp',
            'message_type' => 'required|in:due_reminder,bill_link,history_link',
            'sale_id' => 'nullable|exists:sales,id',
        ]);

        if (empty($customer->phone)) {
            return back()->with('error', 'Customer phone number not available');
        }

        $message = '';
        $whatsappLink = '';
        
        // Build message based on type
        if ($validated['message_type'] === 'due_reminder') {
            $dueAmount = $customer->due_amount;
            $message = "Dear {$customer->name},\n\n";
            $message .= "This is a friendly reminder about your outstanding balance.\n";
            $message .= "Total Due Amount: " . config('app.currency', 'Rs ') . number_format($dueAmount, 2) . "\n\n";
            $message .= "Please make payment at your earliest convenience.\n";
            $message .= "Thank you for your business!\n\n";
            $message .= "- " . config('app.name', 'Vehicle POS');
            
        } elseif ($validated['message_type'] === 'bill_link' && !empty($validated['sale_id'])) {
            $sale = \App\Models\Sale::findOrFail($validated['sale_id']);
            $billUrl = route('customer.bill.view', [$customer->id, $sale->id]);
            
            $message = "Dear {$customer->name},\n\n";
            $message .= "Your invoice #{$sale->sale_no} is ready.\n";
            $message .= "Amount: " . config('app.currency', 'Rs ') . number_format((float)$sale->total_amount, 2) . "\n";
            if ($sale->due_amount > 0) {
                $message .= "Due: " . config('app.currency', 'Rs ') . number_format((float)$sale->due_amount, 2) . "\n";
            }
            $message .= "\nView/Download Bill: {$billUrl}\n\n";
            $message .= "- " . config('app.name', 'Vehicle POS');
            
        } elseif ($validated['message_type'] === 'history_link') {
            $historyUrl = route('customer.history.view', [$customer->id]);
            
            $message = "Dear {$customer->name},\n\n";
            $message .= "View your complete billing history and account statement:\n";
            $message .= "{$historyUrl}\n\n";
            $message .= "- " . config('app.name', 'Vehicle POS');
        }

        // Send via SMS
        if ($validated['channel'] === 'sms') {
            $smsService = new \App\Services\SmsService();
            $sent = $smsService->send($customer->phone, $message);
            
            if ($sent) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment reminder sent via SMS successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send SMS. Please check your SMS configuration.'
                ], 422);
            }
        }
        
        // Send via WhatsApp (open WhatsApp with pre-filled message)
        if ($validated['channel'] === 'whatsapp') {
            $whatsappService = new \App\Services\WhatsAppService();
            $whatsappLink = $whatsappService->generateLink($customer->phone, $message);
            
            return response()->json([
                'success' => true,
                'whatsapp_link' => $whatsappLink,
                'message' => 'WhatsApp link generated. Click to send message.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid channel selected'
        ], 422);
    }

    /**
     * Public view of customer bill (no login required)
     */
    public function viewBill($customer, $sale)
    {
        $customer = Customer::findOrFail($customer);
        $sale = \App\Models\Sale::with('items.product')->findOrFail($sale);
        
        // Verify the sale belongs to this customer
        if ($sale->customer_id != $customer->id) {
            abort(404, 'Bill not found');
        }
        // Hide access completely if sale is in hidden range
        if (SecretPos::isHidden((float)$sale->total_amount)) {
            abort(404, 'Bill not found');
        }
        
        // Get business settings
        $businessName = \App\Models\Setting::get('business_name', config('app.name', 'Vehicle POS'));
        $businessEmail = \App\Models\Setting::get('business_email', '');
        $businessPhone = \App\Models\Setting::get('business_phone', '');
        $businessAddress = \App\Models\Setting::get('business_address', '');
        
        return view('customers.public-bill', compact('customer', 'sale', 'businessName', 'businessEmail', 'businessPhone', 'businessAddress'));
    }

    /**
     * Public view of customer billing history (no login required)
     */
    public function viewHistory($customer)
    {
        $customer = Customer::findOrFail($customer);
        $sales = \App\Models\Sale::with('items')
            ->where('customer_id', $customer->id)
            ->orderBy('sale_date', 'desc')
            ->get()
            ->reject(function($s) { return SecretPos::isHidden((float)$s->total_amount); });
        
        $totalInvoice = $sales->sum('total_amount');
        $totalPaid = $sales->sum('paid_amount');
        $totalDue = $sales->sum('due_amount');
        
        $businessName = \App\Models\Setting::get('business_name', config('app.name', 'Vehicle POS'));
        
        return view('customers.public-history', compact('customer', 'sales', 'totalInvoice', 'totalPaid', 'totalDue', 'businessName'));
    }

    /**
     * Payment page for customer (no login required)
     */
    public function paymentPage($customer, $sale)
    {
        $customer = Customer::findOrFail($customer);
        $sale = \App\Models\Sale::findOrFail($sale);
        
        // Verify the sale belongs to this customer
        if ($sale->customer_id != $customer->id) {
            abort(404, 'Bill not found');
        }
        if (SecretPos::isHidden((float)$sale->total_amount)) {
            abort(404, 'Bill not found');
        }
        
        $businessName = \App\Models\Setting::get('business_name', config('app.name', 'Vehicle POS'));
        $businessEmail = \App\Models\Setting::get('business_email', '');
        $businessPhone = \App\Models\Setting::get('business_phone', '');
        
        return view('customers.public-payment', compact('customer', 'sale', 'businessName', 'businessEmail', 'businessPhone'));
    }
}

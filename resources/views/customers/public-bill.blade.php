<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $displayInvoiceNo ?? 'INV-01' }} - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    @php
        $controls = is_array($controls ?? null) ? $controls : [];
        $priceVisiblePct = (float) ($controls['customer_visible_percentage'] ?? 100);
        $applyPct = function ($value, $pct) {
            $pct = max(0, min(100, (float) $pct));
            return (float) $value * ($pct / 100);
        };
        $maskMoney = function ($value, $forceHide = false) use ($controls, $priceVisiblePct, $applyPct) {
            $raw = (float) $value;
            if ($forceHide || !empty($controls['hide_price_wise_data'])) {
                return '—';
            }

            $masked = $applyPct(abs($raw), $priceVisiblePct);
            $roundToWhole = $priceVisiblePct < 100;

            if ($roundToWhole) {
                $masked = round($masked);
            }

            if ($priceVisiblePct < 100 && abs($raw) > 0 && $masked <= 0) {
                $masked = 1;
            }

            if ($raw < 0) {
                $masked *= -1;
            }

            return number_format($masked, $roundToWhole ? 0 : 2);
        };
        $maskMoneyValue = function ($value, $forceHide = false) use ($controls, $priceVisiblePct, $applyPct) {
            if ($forceHide || !empty($controls['hide_price_wise_data'])) {
                return null;
            }

            $raw = (float) $value;
            $masked = $applyPct(abs($raw), $priceVisiblePct);
            $roundToWhole = $priceVisiblePct < 100;

            if ($roundToWhole) {
                $masked = round($masked);
            }

            if ($priceVisiblePct < 100 && abs($raw) > 0 && $masked <= 0) {
                $masked = 1;
            }

            if ($raw < 0) {
                $masked *= -1;
            }

            return $masked;
        };
        $maskQty = function ($value, $forceHide = false) use ($controls) {
            if ($forceHide || !empty($controls['hide_qty_wise_data']) || !empty($controls['hide_actual_stock_quantity'])) {
                return '—';
            }

            $qty = (float) $value;
            if ($qty > 0 && $qty < 1) {
                $qty = 1;
            }
            if ($qty < 0 && $qty > -1) {
                $qty = -1;
            }

            return number_format(round($qty), 0);
        };
        $displayInvoiceNo = $displayInvoiceNo ?? 'INV-01';
        $hidePayments = !empty($controls['hide_supplier_payments']);
        $hideTotal = !empty($controls['hide_total_sales']);
        $payments = $sale->payments ?? collect();
        $chequePayments = $sale->chequePayments ?? collect();
    @endphp
    <div class="max-w-4xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">{{ $businessName }}</h1>
                    @if($businessEmail || $businessPhone)
                    <div class="text-sm text-gray-600 mt-1">
                        @if($businessEmail)<div><i class="fas fa-envelope mr-1"></i>{{ $businessEmail }}</div>@endif
                        @if($businessPhone)<div><i class="fas fa-phone mr-1"></i>{{ $businessPhone }}</div>@endif
                    </div>
                    @endif
                    @if($businessAddress)
                    <p class="text-sm text-gray-600">{{ $businessAddress }}</p>
                    @endif
                    <p class="text-gray-600 mt-2 font-semibold">Invoice #{{ $displayInvoiceNo }}</p>
                </div>
                <div class="text-right">
                    <button onclick="window.print()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg print:hidden">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                </div>
            </div>

            <!-- Customer & Invoice Info -->
            <div class="grid grid-cols-2 gap-6 mb-8">
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Bill To:</h3>
                    <p class="text-lg font-bold">{{ $customer->name }}</p>
                    @if($customer->phone)
                        <p class="text-gray-600"><i class="fas fa-phone mr-1"></i>{{ $customer->phone }}</p>
                    @endif
                    @if($customer->email)
                        <p class="text-gray-600"><i class="fas fa-envelope mr-1"></i>{{ $customer->email }}</p>
                    @endif
                    @if($customer->address)
                        <p class="text-gray-600 mt-2">{{ $customer->address }}</p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-gray-600">Date: <span class="font-semibold">{{ $sale->sale_date->format('d M, Y') }}</span></p>
                    <p class="text-gray-600">Payment Status: 
                        <span class="px-2 py-1 rounded text-xs font-semibold
                            {{ $sale->payment_status === 'paid' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $sale->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' : '' }}
                            {{ $sale->payment_status === 'due' ? 'bg-red-100 text-red-700' : '' }}">
                            {{ ucfirst($sale->payment_status) }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Items Table -->
            <div class="overflow-x-auto mb-6">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">#</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Product</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Qty</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Price</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($sale->items as $index => $item)
                        @php
                            $lineQty = (float) $item->quantity;
                            $lineUnitMasked = $maskMoneyValue($item->selling_price, !empty($controls['hide_actual_stock_price']));
                            $lineTotalMasked = $lineUnitMasked === null ? null : ($lineQty * $lineUnitMasked);
                        @endphp
                        <tr>
                            <td class="px-4 py-3">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">{{ !empty($controls['hide_product_wise_data']) ? 'Hidden Product' : $item->product->name }}</td>
                            <td class="px-4 py-3 text-center">{{ $maskQty($item->quantity) }}</td>
                            <td class="px-4 py-3 text-right">{{ trim($currency) }} {{ $maskMoney($item->selling_price, !empty($controls['hide_actual_stock_price'])) }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ trim($currency) }} {{ $lineTotalMasked === null ? '—' : number_format($lineTotalMasked, $priceVisiblePct < 100 ? 0 : 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="border-t pt-4">
                <div class="flex justify-end">
                    <div class="w-64">
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-semibold">{{ trim($currency) }} {{ $maskMoney($sale->subtotal) }}</span>
                        </div>
                        @if($sale->discount > 0)
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Discount:</span>
                            <span class="font-semibold text-red-600">{{ trim($currency) }} {{ $maskMoney($sale->discount) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between mb-2 text-lg font-bold border-t pt-2">
                            <span>Total:</span>
                            <span>{{ trim($currency) }} {{ $maskMoney($sale->total_amount, $hideTotal) }}</span>
                        </div>
                        <div class="flex justify-between mb-2 text-green-600">
                            <span>Paid:</span>
                            <span class="font-semibold">{{ trim($currency) }} {{ $maskMoney($sale->paid_amount, $hidePayments) }}</span>
                        </div>
                        @if((float) ($sale->held_cheque_amount ?? 0) > 0)
                        <div class="flex justify-between mb-2 text-indigo-600">
                            <span>Cheque Held:</span>
                            <span class="font-semibold">{{ trim($currency) }} {{ $maskMoney($sale->held_cheque_amount, $hidePayments) }}</span>
                        </div>
                        @endif
                        @if($sale->due_amount > 0)
                        <div class="flex justify-between text-red-600 font-bold text-lg">
                            <span>Due:</span>
                            <span>{{ trim($currency) }} {{ $maskMoney($sale->due_amount, $hidePayments) }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            @if($payments->count() || $chequePayments->count())
            <div class="mt-6 pt-6 border-t">
                <h4 class="font-semibold text-gray-700 mb-3">Payment Details</h4>
                @if($payments->count())
                <div class="space-y-2 mb-4">
                    @foreach($payments as $payment)
                    <div class="flex justify-between rounded-lg bg-gray-50 px-4 py-3 text-sm">
                        <div>
                            <div class="font-semibold text-gray-800">{{ str_replace('_', ' ', ucfirst((string) $payment->payment_method)) }}</div>
                            <div class="text-gray-500">{{ $payment->payment_date?->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div class="font-bold text-green-700">{{ trim($currency) }} {{ $maskMoney($payment->amount, $hidePayments) }}</div>
                    </div>
                    @endforeach
                </div>
                @endif

                @if($chequePayments->count())
                <div class="space-y-2">
                    @foreach($chequePayments as $cheque)
                    <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <div class="font-semibold text-gray-800">Cheque {{ $cheque->cheque_number }}</div>
                            <div class="font-bold text-indigo-700">{{ trim($currency) }} {{ $maskMoney($cheque->amount, $hidePayments) }}</div>
                        </div>
                        <div class="mt-1 grid grid-cols-1 md:grid-cols-2 gap-1 text-gray-600">
                            <div>Pass Date: {{ $cheque->cheque_date?->format('Y-m-d') ?? '-' }}</div>
                            <div>Status: {{ $cheque->status === 'pending' ? 'Hold until passed' : ucfirst($cheque->status) }}</div>
                            @if($cheque->bank_name)<div>Bank: {{ $cheque->bank_name }}</div>@endif
                            @if($cheque->account_name)<div>Name: {{ $cheque->account_name }}</div>@endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            @if($sale->notes)
            <div class="mt-6 pt-6 border-t">
                <h4 class="font-semibold text-gray-700 mb-2">Notes:</h4>
                <p class="text-gray-600">{{ $sale->notes }}</p>
            </div>
            @endif

            @php($invoiceTerms = \App\Models\Setting::get('invoice_terms', ''))
            @if(!empty($invoiceTerms))
            <div class="mt-6 pt-6 border-t">
                <h4 class="font-semibold text-gray-700 mb-2">Terms & Conditions</h4>
                <p class="text-gray-600">{{ $invoiceTerms }}</p>
            </div>
            @endif
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow p-6 print:hidden">
            <h3 class="font-semibold text-gray-800 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="{{ route('customer.history.view', [$customer->id]) }}" class="px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-center transition">
                    <i class="fas fa-history mr-2"></i>View All Bills
                </a>
                <button onclick="window.print()" class="px-4 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition">
                    <i class="fas fa-download mr-2"></i>Download/Print
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-gray-500 text-sm mt-8 print:hidden">
            <p>Thank you for your business!</p>
            @php($dev = config('services.developer'))
            @php($phoneDigits = preg_replace('/\D+/', '', $dev['phone'] ?? ''))
            <p class="mt-2">
                Powered by
                @if(!empty($dev['website']))
                    <a href="https://{{ $dev['website'] }}" target="_blank" class="text-blue-600 hover:text-blue-800 font-semibold">{{ $dev['website'] }}</a>
                @elseif(!empty($phoneDigits))
                    <a href="https://wa.me/{{ $phoneDigits }}" target="_blank" class="text-green-600 hover:text-green-800 font-semibold">{{ $dev['name'] ?? $phoneDigits }}</a>
                @else
                    <span class="font-semibold">{{ $dev['name'] ?? 'Developer' }}</span>
                @endif
            </p>
        </div>
    </div>
</body>
</html>

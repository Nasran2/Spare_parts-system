<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Invoice #{{ $displayInvoiceNo ?? 'INV-01' }}</title>
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
            if ($forceHide || !empty($controls['hide_price_wise_data'])) {
                return '—';
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

            return number_format($masked, $roundToWhole ? 0 : 2);
        };
        $displayInvoiceNo = $displayInvoiceNo ?? 'INV-01';
        $hidePayments = !empty($controls['hide_supplier_payments']);
        $hideTotal = !empty($controls['hide_total_sales']);
    @endphp
    <div class="max-w-2xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">{{ $businessName }}</h1>
                <h2 class="text-xl font-semibold text-gray-700">Payment</h2>
                <p class="text-gray-600">Invoice {{ $displayInvoiceNo }}</p>
            </div>

            <!-- Customer Info -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h3 class="font-semibold text-gray-700 mb-2">Customer Information</h3>
                <p class="text-gray-600">{{ $customer->name }}</p>
                <p class="text-gray-600">{{ $customer->phone }}</p>
                @if($customer->email)
                <p class="text-gray-600">{{ $customer->email }}</p>
                @endif
            </div>

            <!-- Payment Summary -->
            <div class="space-y-3 mb-6">
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span class="text-gray-600">Total Amount:</span>
                    <span class="font-semibold text-gray-800">{{ trim($currency) }} {{ $maskMoney($sale->total_amount, $hideTotal) }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span class="text-gray-600">Paid Amount:</span>
                    <span class="font-semibold text-green-600">{{ trim($currency) }} {{ $maskMoney($sale->paid_amount, $hidePayments) }}</span>
                </div>
                <div class="flex justify-between py-3 border-b-2 border-gray-300">
                    <span class="text-lg font-semibold text-gray-800">Due Amount:</span>
                    <span class="text-xl font-bold text-red-600">{{ trim($currency) }} {{ $maskMoney($sale->due_amount, $hidePayments) }}</span>
                </div>
            </div>

            @if($sale->due_amount > 0)
            <!-- Payment Options -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                <h3 class="font-semibold text-blue-800 mb-4 flex items-center">
                    <i class="fas fa-credit-card mr-2"></i>
                    Payment Options
                </h3>
                <p class="text-blue-700 mb-4">To make a payment for this invoice, please contact us:</p>
                <div class="space-y-2">
                    @if($businessPhone)
                    <div class="flex items-center text-blue-700">
                        <i class="fas fa-phone w-6"></i>
                        <span>Call: {{ $businessPhone }}</span>
                    </div>
                    @endif
                    @if($businessEmail)
                    <div class="flex items-center text-blue-700">
                        <i class="fas fa-envelope w-6"></i>
                        <span>Email: {{ $businessEmail }}</span>
                    </div>
                    @endif
                    @if(!$businessPhone && !$businessEmail)
                    <div class="text-blue-700">
                        <p>Please contact us to arrange payment.</p>
                    </div>
                    @endif
                </div>
            </div>
            @else
            <!-- Paid Status -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6 text-center">
                <i class="fas fa-check-circle text-green-600 text-4xl mb-2"></i>
                <h3 class="font-semibold text-green-800 text-xl">Invoice Fully Paid</h3>
                <p class="text-green-700 mt-2">Thank you for your payment!</p>
            </div>
            @endif

            <!-- Actions -->
            <div class="flex gap-3">
                <a href="{{ route('customer.bill.view', [$customer->id, $sale->id]) }}" 
                   class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-center px-6 py-3 rounded-lg font-semibold">
                    <i class="fas fa-file-invoice mr-2"></i>View Invoice
                </a>
                <a href="{{ route('customer.history.view', [$customer->id]) }}" 
                   class="flex-1 bg-gray-600 hover:bg-gray-700 text-white text-center px-6 py-3 rounded-lg font-semibold">
                    <i class="fas fa-history mr-2"></i>View History
                </a>
            </div>

            <div class="mt-6 text-center text-sm text-gray-500">
                Generated on {{ \Carbon\Carbon::now()->format('d M Y H:i A') }}
            </div>
        </div>
    </div>
</body>
</html>

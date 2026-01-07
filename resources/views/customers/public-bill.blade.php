<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $sale->sale_no }} - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
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
                    <p class="text-gray-600 mt-2 font-semibold">Invoice #{{ $sale->sale_no }}</p>
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
                        <tr>
                            <td class="px-4 py-3">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">{{ $item->product->name }}</td>
                            <td class="px-4 py-3 text-center">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-right">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $item->selling_price, $currency) }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $item->subtotal, $currency) }}</td>
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
                            <span class="font-semibold">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->subtotal, $currency) }}</span>
                        </div>
                        @if($sale->discount > 0)
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Discount:</span>
                            <span class="font-semibold text-red-600">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->discount, $currency) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between mb-2 text-lg font-bold border-t pt-2">
                            <span>Total:</span>
                            <span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->total_amount, $currency) }}</span>
                        </div>
                        <div class="flex justify-between mb-2 text-green-600">
                            <span>Paid:</span>
                            <span class="font-semibold">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->paid_amount, $currency) }}</span>
                        </div>
                        @if($sale->due_amount > 0)
                        <div class="flex justify-between text-red-600 font-bold text-lg">
                            <span>Due:</span>
                            <span>{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->due_amount, $currency) }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

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

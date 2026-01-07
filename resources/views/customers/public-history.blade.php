<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing History - {{ $customer->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">{{ $businessName }} - Billing History</h1>
                    <p class="text-gray-600 mt-2">Customer: <span class="font-semibold">{{ $customer->name }}</span></p>
                    <p class="text-gray-600">Phone: {{ $customer->phone }}</p>
                    @if($customer->email)
                    <p class="text-gray-600">Email: {{ $customer->email }}</p>
                    @endif
                </div>
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg print:hidden">
                    <i class="fas fa-print mr-2"></i>Print
                </button>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <div class="text-sm text-blue-600 font-semibold">Total Invoice</div>
                    <div class="text-2xl font-bold text-blue-800">{{ $currency }}{{ number_format($totalInvoice, 2) }}</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                    <div class="text-sm text-green-600 font-semibold">Total Paid</div>
                    <div class="text-2xl font-bold text-green-800">{{ $currency }}{{ number_format($totalPaid, 2) }}</div>
                </div>
                <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                    <div class="text-sm text-red-600 font-semibold">Total Due</div>
                    <div class="text-2xl font-bold text-red-800">{{ $currency }}{{ number_format($totalDue, 2) }}</div>
                </div>
            </div>

            <!-- Invoices Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Due</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider print:hidden">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($sales as $sale)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                #{{ str_pad($sale->id, 6, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                {{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->total_amount, $currency) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600">
                                {{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->paid_amount, $currency) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600">
                                {{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->due_amount, $currency) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($sale->payment_status == 'paid')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Paid
                                    </span>
                                @elseif($sale->payment_status == 'partial')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        Partial
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Due
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm print:hidden">
                                <a href="{{ route('customer.bill.view', [$customer->id, $sale->id]) }}" 
                                   class="text-blue-600 hover:text-blue-900" target="_blank">
                                    <i class="fas fa-eye mr-1"></i>View
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2"></i>
                                <p>No invoices found</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6 text-center text-sm text-gray-500">
                Generated on {{ \Carbon\Carbon::now()->format('d M Y H:i A') }}
            </div>
        </div>
    </div>

    <style>
        @media print {
            body { background: white; }
            .print\:hidden { display: none !important; }
            .shadow-lg { box-shadow: none; }
        }
    </style>
</body>
</html>

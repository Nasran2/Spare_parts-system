<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-bold text-gray-800">Pre-Orders for {{ $customer->name }}</h3>
        <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeCustomerModal()">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>

    <div class="mb-6">
        <h4 class="font-semibold text-gray-700 mb-2 border-b pb-1">Pre-Orders</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2">Pre-Order #</th>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Vehicle</th>
                        <th class="px-3 py-2 text-center">Status</th>
                        <th class="px-3 py-2 text-center">Payment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($preOrders as $order)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-mono font-bold text-blue-700">
                                <a href="{{ route('preorders.show', $order) }}" class="hover:underline">
                                    {{ $order->pre_order_number }}
                                </a>
                            </td>
                            <td class="px-3 py-2">{{ $order->pre_order_date->format('Y-m-d') }}</td>
                            <td class="px-3 py-2">{{ $order->vehicle_name ?: '—' }}</td>
                            <td class="px-3 py-2 text-center">
                                @php $statusColors = ['pending'=>'bg-amber-100 text-amber-800','completed'=>'bg-green-100 text-green-800','cancelled'=>'bg-red-100 text-red-800']; @endphp
                                <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $statusColors[$order->status] ?? 'bg-gray-100' }}">{{ ucfirst($order->status) }}</span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                @php $paymentColors = ['unpaid'=>'bg-red-100 text-red-800','partial'=>'bg-amber-100 text-amber-800','paid'=>'bg-green-100 text-green-800']; @endphp
                                <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $paymentColors[$order->payment_status] ?? 'bg-gray-100' }}">{{ ucfirst($order->payment_status) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-gray-500 italic">No pre-orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <h4 class="font-semibold text-gray-700 mb-2 border-b pb-1">Payments Made</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Pre-Order #</th>
                        <th class="px-3 py-2">Method</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2">{{ optional($payment->payment_date)->format('Y-m-d') ?? $payment->created_at->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 font-mono">
                                <a href="{{ route('preorders.show', $payment->preOrder) }}" class="text-blue-600 hover:underline">
                                    {{ $payment->preOrder->pre_order_number }}
                                </a>
                            </td>
                            <td class="px-3 py-2 uppercase text-xs">{{ $payment->payment_method }}</td>
                            <td class="px-3 py-2 text-right font-semibold text-green-700">
                                {{ config('app.currency', 'Rs ') }}{{ number_format($payment->amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500 italic">No payments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

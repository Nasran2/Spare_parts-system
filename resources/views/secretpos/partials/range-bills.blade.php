<div class="w-full">
    <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Invoice</th>
                    <th class="px-4 py-2 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Date</th>
                    <th class="px-4 py-2 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Customer</th>
                    <th class="px-4 py-2 text-right text-xs font-bold text-slate-600 uppercase tracking-wider">Amount</th>
                    <th class="px-4 py-2 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-100">
                @forelse($bills as $sale)
                    <tr>
                        <td class="px-4 py-2 text-sm text-slate-800">{{ $sale->sale_no }}</td>
                        <td class="px-4 py-2 text-sm text-slate-700">{{ optional($sale->sale_date)->format('Y-m-d') ?? $sale->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-2 text-sm text-slate-700">{{ $sale->customer->name ?? 'Walk-in Customer' }}</td>
                        <td class="px-4 py-2 text-sm text-slate-900 text-right">{{ trim($currency) }} {{ number_format((float)$sale->total_amount, 2) }}</td>
                        <td class="px-4 py-2 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">{{ $sale->payment_status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500 text-sm">No bills found in this range.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-slate-500">Showing up to 300 recent bills.</p>
</div>

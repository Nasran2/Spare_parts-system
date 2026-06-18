@extends('layouts.app')

@section('title', 'Quotations')
@section('page-title', 'Quotation Management')

@section('content')
<div class="space-y-6">

    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Quotation Management</h3>
            <p class="text-sm text-gray-600">View and manage draft quotations</p>
        </div>
        <a href="{{ route('quotations.create') }}" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg">
            <i class="fas fa-file-invoice mr-2"></i>Create Quotation
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white rounded-xl shadow-md p-4 space-y-4">
        <input type="hidden" name="sale_type" value="quotation">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div>
                <label class="text-xs font-semibold text-gray-600">Date From</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="mt-1 w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Date To</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="mt-1 w-full px-3 py-2 border rounded-lg">
            </div>
            @include('partials.quick-date-filter', ['fromName' => 'date_from', 'toName' => 'date_to'])
            <div>
                <label class="text-xs font-semibold text-gray-600">Customer</label>
                <select name="customer_id" class="mt-1 w-full px-3 py-2 border rounded-lg">
                    <option value="">All</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" @selected(($filters['customer_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Payment Status</label>
                <select name="payment_status" class="mt-1 w-full px-3 py-2 border rounded-lg">
                    <option value="">All</option>
                    @foreach(['paid','partial','unpaid'] as $st)
                        <option value="{{ $st }}" @selected(($filters['payment_status'] ?? '') === $st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end space-x-2">
                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg">Filter</button>
                <a href="{{ route('quotations.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Reset</a>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 pt-2">
            <a href="{{ route('sales.export.csv', array_merge(request()->query(), ['sale_type' => 'quotation'])) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-green-600 text-white rounded text-sm"><i class="fas fa-file-csv mr-1"></i>CSV</a>
            <a href="{{ route('sales.export.pdf', array_merge(request()->query(), ['sale_type' => 'quotation'])) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-red-600 text-white rounded text-sm"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
        </div>
    </form>

    <div class="mt-4">
        <label class="text-xs font-semibold text-gray-600">Quick search</label>
        <input id="quotationsSearchInput" type="search" placeholder="Search quotations" class="mt-2 w-full px-4 py-2 border rounded-lg focus:border-blue-500 focus:ring focus:ring-blue-100">
    </div>

    <!-- Quotations Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Quotation No</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Customer</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Type</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($sales as $sale)
                        <tr data-quotation-row class="hover:bg-gray-50 transition" data-search-text="{{ strtolower($sale->sale_no . ' ' . ($sale->customer->name ?? '') . ' ' . ($sale->payment_status ?? '')) }}">
                            <td class="px-6 py-4"><span class="font-mono font-semibold text-amber-600">{{ $sale->sale_no }}</span></td>
                            <td class="px-6 py-4">{{ $sale->customer->name ?? 'Walk-in Customer' }}</td>
                            <td class="px-6 py-4">{{ $sale->sale_date?->format('M d, Y') ?? $sale->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-right"><span class="font-semibold text-gray-800">{{ \App\Support\SecretPos::currencyMaskForSale($sale->total_amount, $sale->total_amount, $currency) }}</span></td>
                            
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex h-3 w-3 rounded-full bg-amber-500" title="Quotation"></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <a href="{{ route('sales.show', $sale->id) }}" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('sales.print', $sale->id) }}" target="_blank" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="Thermal Print"><i class="fas fa-print"></i></a>
                                    <a href="{{ route('quotations.pdf', $sale->id) }}" target="_blank" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Download PDF"><i class="fas fa-file-pdf"></i></a>
                                    <form action="{{ route('quotations.convert', $sale->id) }}" method="POST" onsubmit="return confirm('Convert this quotation to a sale? Stock will be reduced.');">
                                        @csrf
                                        <button type="submit" class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg transition" title="Convert to Sale">
                                            <i class="fas fa-exchange-alt"></i>
                                        </button>
                                    </form>
                                    @if(auth()->user()?->hasPermission('sales.delete'))
                                        <form action="{{ route('sales.destroy', $sale->id) }}" method="POST" onsubmit="return confirm('Delete this quotation? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 text-red-700 hover:bg-red-50 rounded-lg transition" title="Delete Quotation">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <i class="fas fa-file-invoice-dollar text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 text-lg">No quotations found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $sales->links() }}</div>
    </div>

    <script>
        const quotationsSearchInput = document.getElementById('quotationsSearchInput');
        const quotationRows = document.querySelectorAll('[data-quotation-row]');
        quotationsSearchInput?.addEventListener('input', function () {
            const term = this.value.trim().toLowerCase();
            quotationRows.forEach(row => {
                const text = row.dataset.searchText || '';
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    </script>

</div>
@endsection

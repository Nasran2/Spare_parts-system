@extends('layouts.app')

@section('title', 'Transfer History')
@section('page-title', 'Transfer History')

@section('content')
<div class="space-y-6">
    <!-- Filter Section -->
    <div class="bg-white p-5 rounded-lg shadow border border-gray-100">
        <form method="GET" action="{{ route('stores.transfer-report') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">From Store</label>
                <select name="from_store_id" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Stores</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ request('from_store_id') == $store->id ? 'selected' : '' }}>{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">To Store</label>
                <select name="to_store_id" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Stores</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ request('to_store_id') == $store->id ? 'selected' : '' }}>{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Search Product/Ref</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="md:col-span-5 flex justify-end gap-2 mt-2">
                <a href="{{ route('stores.transfer-report') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200 transition-colors">Reset</a>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">Filter</button>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b">
                    <tr>
                        <th class="px-5 py-4 text-left font-semibold">Transfer No</th>
                        <th class="px-5 py-4 text-left font-semibold">Date</th>
                        <th class="px-5 py-4 text-left font-semibold">From Store</th>
                        <th class="px-5 py-4 text-left font-semibold">To Store</th>
                        <th class="px-5 py-4 text-center font-semibold">Items</th>
                        <th class="px-5 py-4 text-right font-semibold">Total Qty</th>
                        <th class="px-5 py-4 text-center font-semibold w-32">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-gray-700">
                    @forelse($transfers as $transfer)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 font-medium whitespace-nowrap">{{ $transfer->transfer_no }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">{{ $transfer->transfer_date?->format('Y-m-d') }}</td>
                            <td class="px-5 py-3">{{ $transfer->fromStore->name ?? '-' }}</td>
                            <td class="px-5 py-3">{{ $transfer->toStore->name ?? '-' }}</td>
                            <td class="px-5 py-3 text-center">
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">{{ $transfer->items->count() }} Products</span>
                            </td>
                            <td class="px-5 py-3 text-right font-bold text-gray-900">{{ number_format((float) $transfer->items->sum('quantity'), 3) }}</td>
                            <td class="px-5 py-3 text-center whitespace-nowrap">
                                @php
                                    $viewData = [
                                        "id" => $transfer->id,
                                        "transfer_no" => $transfer->transfer_no,
                                        "date" => $transfer->transfer_date?->format("Y-m-d"),
                                        "from" => $transfer->fromStore->name ?? "-",
                                        "to" => $transfer->toStore->name ?? "-",
                                        "reference" => $transfer->reference_no,
                                        "notes" => $transfer->notes,
                                        "shipping" => number_format((float) $transfer->shipping_cost, 2),
                                        "additional" => number_format((float) $transfer->additional_expense, 2),
                                        "created_at" => $transfer->created_at?->format("Y-m-d H:i:s"),
                                        "items" => $transfer->items->map(function($item) {
                                            return [
                                                "name" => $item->product->name ?? "-",
                                                "sku" => $item->product->sku ?? "-",
                                                "qty" => number_format((float) $item->quantity, 3)
                                            ];
                                        })
                                    ];
                                    $editData = [
                                        "id" => $transfer->id,
                                        "date" => $transfer->transfer_date?->format("Y-m-d"),
                                        "reference" => $transfer->reference_no,
                                        "notes" => $transfer->notes,
                                        "shipping" => (float) $transfer->shipping_cost,
                                        "additional" => (float) $transfer->additional_expense,
                                        "items" => $transfer->items->map(function($item) {
                                            return [
                                                "id" => $item->product_id,
                                                "name" => $item->product->name ?? "-",
                                                "sku" => $item->product->sku ?? "-",
                                                "qty" => (float) $item->quantity
                                            ];
                                        })
                                    ];
                                @endphp
                                <button type="button" class="text-indigo-600 hover:text-indigo-900 mx-1 transition-colors" title="View Details" onclick='viewTransferDetails(@json($viewData))'>
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="text-blue-600 hover:text-blue-900 mx-1 transition-colors" title="Edit" onclick='editTransfer(@json($editData))'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="{{ route('stores.transfers.destroy', $transfer->id) }}" class="inline-block m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this transfer? This will revert the stock back to the source store.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 mx-1 transition-colors" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-3xl mb-3 text-gray-300 block"></i>
                                No transfers found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transfers->hasPages())
            <div class="px-5 py-4 border-t border-gray-100 bg-gray-50">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>
</div>

<!-- View Transfer Details Modal -->
<div id="viewTransferModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-gray-900/50 backdrop-blur-sm transition-opacity">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="relative w-full max-w-3xl rounded-2xl bg-white shadow-2xl overflow-hidden transform transition-all">
            <div class="flex items-center justify-between border-b px-6 py-4 bg-gray-50">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-file-invoice text-indigo-600"></i> Transfer Details <span id="view_transfer_no" class="text-gray-500 font-normal"></span>
                </h3>
                <button type="button" onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 hover:bg-gray-200 rounded-full w-8 h-8 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="space-y-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <div>
                            <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">From Store</span>
                            <span class="block text-sm font-medium text-gray-900" id="view_from"></span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">To Store</span>
                            <span class="block text-sm font-medium text-gray-900" id="view_to"></span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">Transfer Date</span>
                            <span class="block text-sm text-gray-900" id="view_date"></span>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">Reference No</span>
                            <span class="block text-sm text-gray-900" id="view_ref"></span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider">Created At</span>
                            <span class="block text-sm text-gray-900" id="view_created"></span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Notes</span>
                            <div class="text-sm text-gray-700 bg-gray-50 p-2 rounded border border-gray-100 min-h-[2rem]" id="view_notes"></div>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <h4 class="text-sm font-bold text-gray-800 mb-2 border-b pb-2">Products Transferred</h4>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">#</th>
                                    <th class="px-4 py-2 text-left font-semibold">Product</th>
                                    <th class="px-4 py-2 text-left font-semibold">SKU</th>
                                    <th class="px-4 py-2 text-right font-semibold">Qty</th>
                                </tr>
                            </thead>
                            <tbody id="view_items_body" class="divide-y divide-gray-200">
                                <!-- Populated via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-red-50 p-3 rounded-lg border border-red-100">
                        <span class="block text-xs font-semibold text-red-600 uppercase tracking-wider">Shipping Cost</span>
                        <span class="block text-sm font-bold text-red-700" id="view_shipping"></span>
                    </div>
                    <div class="bg-orange-50 p-3 rounded-lg border border-orange-100">
                        <span class="block text-xs font-semibold text-orange-600 uppercase tracking-wider">Additional Expenses</span>
                        <span class="block text-sm font-bold text-orange-700" id="view_additional"></span>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4 border-t flex justify-end">
                <button type="button" onclick="closeViewModal()" class="px-5 py-2 bg-white border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Transfer Modal -->
<div id="editTransferModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-gray-900/50 backdrop-blur-sm transition-opacity">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between border-b px-6 py-4 bg-gray-50">
                <h3 class="text-lg font-bold text-gray-900">Edit Transfer</h3>
                <button type="button" onclick="closeEditTransferModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form id="editTransferForm" method="POST" action="" class="p-6">
                @csrf
                @method('PUT')
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Transfer Date <span class="text-red-500">*</span></label>
                            <input type="date" name="transfer_date" id="edit_transfer_date" class="w-full border rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Reference No</label>
                            <input type="text" name="reference_no" id="edit_transfer_reference" class="w-full border rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-bold text-gray-800 mb-2 border-b pb-2 mt-4">Products Transferred</h4>
                        <p class="text-xs text-gray-500 mb-2">Changing quantity will automatically adjust stock at both stores. Ensure source store has enough stock.</p>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg max-h-64 overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-500 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold">Product</th>
                                        <th class="px-4 py-2 text-right font-semibold">Qty</th>
                                    </tr>
                                </thead>
                                <tbody id="edit_items_body" class="divide-y divide-gray-200">
                                    <!-- Populated via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
                        <input type="text" name="notes" id="edit_transfer_notes" class="w-full border rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Shipping Cost</label>
                            <input type="number" step="0.01" min="0" name="shipping_cost" id="edit_transfer_shipping" class="w-full border rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Additional Exp.</label>
                            <input type="number" step="0.01" min="0" name="additional_expense" id="edit_transfer_additional" class="w-full border rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="closeEditTransferModal()" class="rounded-lg border px-5 py-2 font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 font-semibold text-white hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function viewTransferDetails(data) {
        document.getElementById('view_transfer_no').textContent = `(#${data.transfer_no})`;
        document.getElementById('view_date').textContent = data.date;
        document.getElementById('view_from').textContent = data.from;
        document.getElementById('view_to').textContent = data.to;
        document.getElementById('view_ref').textContent = data.reference || '-';
        document.getElementById('view_created').textContent = data.created_at || '-';
        document.getElementById('view_notes').textContent = data.notes || 'No notes provided.';
        document.getElementById('view_shipping').textContent = data.shipping;
        document.getElementById('view_additional').textContent = data.additional;
        
        const tbody = document.getElementById('view_items_body');
        tbody.innerHTML = data.items.map((item, index) => `
            <tr>
                <td class="px-4 py-2 text-gray-600">${index + 1}</td>
                <td class="px-4 py-2 font-medium text-gray-800">${item.name}</td>
                <td class="px-4 py-2 text-gray-500">${item.sku}</td>
                <td class="px-4 py-2 text-right font-bold">${item.qty}</td>
            </tr>
        `).join('');

        document.getElementById('viewTransferModal').classList.remove('hidden');
    }

    function closeViewModal() {
        document.getElementById('viewTransferModal').classList.add('hidden');
    }

    function editTransfer(data) {
        document.getElementById('edit_transfer_date').value = data.date;
        document.getElementById('edit_transfer_reference').value = data.reference || '';
        document.getElementById('edit_transfer_notes').value = data.notes || '';
        document.getElementById('edit_transfer_shipping').value = data.shipping || 0;
        document.getElementById('edit_transfer_additional').value = data.additional || 0;
        
        const tbody = document.getElementById('edit_items_body');
        tbody.innerHTML = data.items.map((item, index) => `
            <tr>
                <td class="px-4 py-2">
                    <div class="font-medium text-gray-800">${item.name}</div>
                    <div class="text-xs text-gray-500">${item.sku}</div>
                    <input type="hidden" name="items[${index}][product_id]" value="${item.id}">
                </td>
                <td class="px-4 py-2 text-right">
                    <input type="number" step="any" min="0.001" name="items[${index}][quantity]" value="${item.qty}" class="w-24 border rounded px-2 py-1 text-right focus:border-blue-500" required>
                </td>
            </tr>
        `).join('');

        const form = document.getElementById('editTransferForm');
        form.action = `{{ url('stores/transfers') }}/${data.id}`;
        
        document.getElementById('editTransferModal').classList.remove('hidden');
    }

    function closeEditTransferModal() {
        document.getElementById('editTransferModal').classList.add('hidden');
    }
</script>
@endpush

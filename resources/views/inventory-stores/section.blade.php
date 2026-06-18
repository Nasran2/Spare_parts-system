@extends('layouts.app')

@php
    $titles = [
        'overview' => 'Store Stock Overview',
        'stores' => 'Stores',
        'allocations' => 'Shipment Allocations',
        'transfers' => 'Store Transfers',
        'report' => 'Shipment Stock Report',
    ];
@endphp

@section('title', $titles[$section] ?? 'Store Stock')
@section('page-title', $titles[$section] ?? 'Store Stock')

@section('content')
<div class="space-y-6">
    @if(session('success'))<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ $errors->first() }}</div>@endif

    @if($section === 'overview')
        @php
            $today = \Carbon\Carbon::today()->format('Y-m-d');
            $yesterday = \Carbon\Carbon::yesterday()->format('Y-m-d');
            $start = request('from') ?: ($from ?? $today);
            $end = request('to') ?: ($to ?? $today);
            $startC = \Carbon\Carbon::parse($start);
            $endC = \Carbon\Carbon::parse($end);
            $isToday = ($start === $today && $end === $today);
            $isYesterday = ($start === $yesterday && $end === $yesterday);
            $isThisWeek = ($endC->isSameDay(\Carbon\Carbon::today()) && $startC->isSameDay($endC->copy()->startOfWeek()));
            $isThisMonth = ($endC->isSameDay(\Carbon\Carbon::today()) && $startC->isSameDay($endC->copy()->startOfMonth()));
            $periodLabel = $start === $end ? $start : $start . ' to ' . $end;
        @endphp

        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <i class="fas fa-calendar-alt"></i>
                        </span>
                        <div>
                            <div class="text-sm font-semibold text-gray-500">Filter Period</div>
                            <div class="text-base font-bold text-gray-800">{{ $periodLabel }}</div>
                        </div>
                    </div>
                    <p id="store_custom_error" class="hidden text-sm font-semibold text-red-600">Select start and end dates.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-store-range="today" class="px-4 py-2 {{ $isToday ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 hover:text-white transition"><i class="fas fa-calendar-day mr-1"></i> Today</button>
                    <button type="button" data-store-range="yesterday" class="px-4 py-2 {{ $isYesterday ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 hover:text-white transition"><i class="fas fa-history mr-1"></i> Yesterday</button>
                    <button type="button" data-store-range="this_week" class="px-4 py-2 {{ $isThisWeek ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 hover:text-white transition"><i class="fas fa-calendar-week mr-1"></i> This Week</button>
                    <button type="button" data-store-range="this_month" class="px-4 py-2 {{ $isThisMonth ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 hover:text-white transition"><i class="fas fa-calendar-alt mr-1"></i> This Month</button>
                    <input type="date" id="store_custom_start" value="{{ request('from') }}" class="px-3 py-2 border rounded text-sm">
                    <input type="date" id="store_custom_end" value="{{ request('to') }}" class="px-3 py-2 border rounded text-sm">
                    <button type="button" data-store-range="custom" class="px-4 py-2 {{ !($isToday || $isYesterday || $isThisWeek || $isThisMonth) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded-lg text-sm hover:bg-blue-700 hover:text-white transition"><i class="fas fa-calendar mr-1"></i> Custom Range</button>
                    @if(request()->hasAny(['from', 'to']))
                        <a href="{{ route('stores.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">Reset</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 md:gap-6">
            <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between gap-4">
                    <div><p class="text-sm text-gray-600 mb-1">Stores</p><h3 class="text-2xl font-bold text-gray-800">{{ number_format((int) ($overviewStats['stores'] ?? 0)) }}</h3><p class="text-xs text-blue-600 mt-2">Active locations</p></div>
                    <div class="w-12 h-12 md:w-14 md:h-14 shrink-0 gradient-blue rounded-full flex items-center justify-center shadow-lg"><i class="fas fa-shop text-white text-xl"></i></div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between gap-4">
                    <div><p class="text-sm text-gray-600 mb-1">Total Stock Qty</p><h3 class="text-2xl font-bold text-gray-800">{{ number_format((float) ($overviewStats['stock_qty'] ?? 0), 3) }}</h3><p class="text-xs text-green-600 mt-2">Current quantity</p></div>
                    <div class="w-12 h-12 md:w-14 md:h-14 shrink-0 gradient-green rounded-full flex items-center justify-center shadow-lg"><i class="fas fa-boxes-stacked text-white text-xl"></i></div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between gap-4">
                    <div><p class="text-sm text-gray-600 mb-1">Allocated Qty</p><h3 class="text-2xl font-bold text-gray-800">{{ number_format((float) ($overviewStats['allocated_qty'] ?? 0), 3) }}</h3><p class="text-xs text-orange-600 mt-2">Selected period</p></div>
                    <div class="w-12 h-12 md:w-14 md:h-14 shrink-0 gradient-orange rounded-full flex items-center justify-center shadow-lg"><i class="fas fa-dolly text-white text-xl"></i></div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between gap-4">
                    <div><p class="text-sm text-gray-600 mb-1">Transfers</p><h3 class="text-2xl font-bold text-gray-800">{{ number_format((float) ($overviewStats['transfer_qty'] ?? 0), 3) }}</h3><p class="text-xs text-red-600 mt-2">{{ number_format((int) ($overviewStats['transfers'] ?? 0)) }} transfer records</p></div>
                    <div class="w-12 h-12 md:w-14 md:h-14 shrink-0 gradient-red rounded-full flex items-center justify-center shadow-lg"><i class="fas fa-right-left text-white text-xl"></i></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <a href="{{ route('stores.stores') }}" class="bg-white rounded-lg shadow p-5 hover:bg-blue-50"><i class="fas fa-shop text-blue-600 mb-3"></i><p class="font-semibold">Stores</p></a>
            <a href="{{ route('stores.allocations') }}" class="bg-white rounded-lg shadow p-5 hover:bg-indigo-50"><i class="fas fa-boxes-stacked text-indigo-600 mb-3"></i><p class="font-semibold">Allocations</p></a>
            <a href="{{ route('stores.transfers') }}" class="bg-white rounded-lg shadow p-5 hover:bg-orange-50"><i class="fas fa-right-left text-orange-600 mb-3"></i><p class="font-semibold">Transfers</p></a>
            <a href="{{ route('stores.report') }}" class="bg-white rounded-lg shadow p-5 hover:bg-gray-50"><i class="fas fa-chart-column text-gray-700 mb-3"></i><p class="font-semibold">Shipment Report</p></a>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-5 border-b flex items-center justify-between"><h3 class="font-semibold text-gray-800"><i class="fas fa-right-left text-orange-600 mr-2"></i>Recent Transfers</h3><a href="{{ route('stores.transfers') }}" class="text-sm font-semibold text-blue-600 hover:underline">View All</a></div>
                <div class="divide-y">@forelse($transfers->take(5) as $transfer)<div class="px-5 py-4 flex items-center justify-between gap-4"><div class="min-w-0"><div class="font-semibold text-gray-800 truncate">{{ $transfer->product->name ?? 'Product' }}</div><div class="text-xs text-gray-500">{{ $transfer->transfer_date?->format('M d, Y') }} · {{ $transfer->reference_no ?: 'No reference' }}</div></div><div class="font-bold text-orange-700">{{ number_format((float) $transfer->quantity, 3) }}</div></div>@empty<div class="px-5 py-10 text-center text-gray-500">No transfers found.</div>@endforelse</div>
            </div>
        </div>
    @endif

    @if($section === 'stores')
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 bg-white rounded-lg shadow overflow-hidden"><div class="p-5 border-b"><h3 class="font-semibold text-gray-800">Stores & POS Stock</h3></div><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3 text-left">Store</th><th class="px-4 py-3 text-left">Code</th><th class="px-4 py-3 text-left">Products</th><th class="px-4 py-3 text-right">Total Qty</th></tr></thead><tbody class="divide-y">@foreach($stores as $store)<tr><td class="px-4 py-3 font-semibold flex items-center">{{ $store->name }} @if($store->is_default)<span class="ml-2 text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Default</span>@else<form method="POST" action="{{ route('stores.set-default', $store->id) }}" class="inline ml-2">@csrf<button type="submit" class="text-xs text-blue-600 hover:text-blue-800 underline">Set as Default</button></form>@endif</td><td class="px-4 py-3">{{ $store->code }}</td><td class="px-4 py-3">{{ $store->stocks->count() }}</td><td class="px-4 py-3 text-right">{{ number_format($store->stocks->sum('quantity'), 3) }}</td></tr>@endforeach</tbody></table></div></div>
            <div class="bg-white rounded-lg shadow p-5"><h3 class="font-semibold text-gray-800 mb-4">Create Store</h3><form method="POST" action="{{ route('stores.store') }}" class="space-y-3">@csrf<input name="name" placeholder="Store name" class="w-full border rounded-lg px-3 py-2" required><input name="code" placeholder="Code" class="w-full border rounded-lg px-3 py-2" required><input name="phone" placeholder="Phone" class="w-full border rounded-lg px-3 py-2"><input name="address" placeholder="Address" class="w-full border rounded-lg px-3 py-2"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1"> Default POS store</label><button class="w-full bg-blue-600 text-white rounded-lg px-4 py-2">Save Store</button></form></div>
        </div>
    @endif


    @if($section === 'allocations')
        <div class="bg-white rounded-lg shadow p-5"><h3 class="font-semibold text-gray-800 mb-4">Allocate Shipment Stock</h3><form method="POST" action="{{ route('stores.shipments.allocate') }}" class="space-y-3">@csrf<select name="stock_shipment_item_id" class="w-full border rounded-lg px-3 py-2" required><option value="">Shipment item</option>@foreach($shipments as $shipment)@foreach($shipment->items as $item)<option value="{{ $item->id }}">{{ $shipment->grn_no }} - {{ $item->product->name }} ({{ number_format($item->quantity - $item->allocations->sum('quantity'), 3) }} left)</option>@endforeach @endforeach</select><select name="store_id" class="w-full border rounded-lg px-3 py-2" required><option value="">Store</option>@foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->name }}</option>@endforeach</select><input type="number" step="0.001" name="quantity" placeholder="Qty to allocate" class="w-full border rounded-lg px-3 py-2" required><button class="w-full bg-blue-600 text-white rounded-lg px-4 py-2">Allocate</button></form></div>
    @endif

    @if($section === 'transfers')
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Transfer Between Stores</h3>
            <form method="POST" action="{{ route('stores.transfers.store') }}" id="transferForm" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="transfer-store-picker" data-role="from">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">From Store <span class="text-red-500">*</span></label>
                        <input type="hidden" name="from_store_id" id="transfer_from_store_id" required>
                        <input type="text" id="transfer_from_store_search" placeholder="Search from store..." class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <div id="transfer_from_store_chip" class="flex flex-wrap gap-2 mt-2"></div>
                        <div id="transfer_from_store_results" class="hidden mt-2 max-h-36 overflow-y-auto border border-gray-300 rounded-lg bg-white p-2 space-y-1">
                            @foreach($stores as $store)
                                <button type="button" class="transfer-store-option flex w-full items-center justify-between rounded p-2 text-left hover:bg-blue-50" data-role="from" data-store-id="{{ $store->id }}" data-store-name="{{ $store->name }}">
                                    <span class="font-semibold text-gray-700">{{ $store->name }}{{ $store->is_default ? ' ⭐' : '' }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="transfer-store-picker" data-role="to">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">To Store <span class="text-red-500">*</span></label>
                        <input type="hidden" name="to_store_id" id="transfer_to_store_id" required>
                        <input type="text" id="transfer_to_store_search" placeholder="Search to store..." class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <div id="transfer_to_store_chip" class="flex flex-wrap gap-2 mt-2"></div>
                        <div id="transfer_to_store_results" class="hidden mt-2 max-h-36 overflow-y-auto border border-gray-300 rounded-lg bg-white p-2 space-y-1">
                            @foreach($stores as $store)
                                <button type="button" class="transfer-store-option flex w-full items-center justify-between rounded p-2 text-left hover:bg-blue-50" data-role="to" data-store-id="{{ $store->id }}" data-store-name="{{ $store->name }}">
                                    <span class="font-semibold text-gray-700">{{ $store->name }}{{ $store->is_default ? ' ⭐' : '' }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Transfer Date <span class="text-red-500">*</span></label>
                        <input type="date" name="transfer_date" value="{{ date('Y-m-d') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Reference</label>
                        <input name="reference_no" placeholder="Reference" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Products <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="text" id="transfer_product_search" placeholder="Search product name / SKU / barcode" class="w-full border border-gray-300 rounded-lg px-3 py-2 pl-10 focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                    <div id="transfer_product_results" class="hidden mt-2 max-h-56 overflow-y-auto border border-gray-300 rounded-lg bg-white p-2 space-y-1"></div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-green-600 text-white">
                            <tr>
                                <th class="px-3 py-3 text-left w-12">#</th>
                                <th class="px-3 py-3 text-left">Product</th>
                                <th class="px-3 py-3 text-right">Available</th>
                                <th class="px-3 py-3 text-right">Transfer Qty</th>
                                <th class="px-3 py-3 text-center w-14"><i class="fas fa-trash"></i></th>
                            </tr>
                        </thead>
                        <tbody id="transferItemsBody" class="divide-y divide-gray-200">
                            <tr id="transferEmptyRow">
                                <td colspan="5" class="px-3 py-8 text-center text-gray-500">Select products to transfer.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Shipping Expense</label>
                        <input type="number" min="0" step="0.01" name="shipping_cost" id="transfer_shipping_cost" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Additional Expense</label>
                        <input type="number" min="0" step="0.01" name="additional_expense" id="transfer_additional_expense" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                        <input name="notes" placeholder="Notes" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 rounded-lg bg-gray-50 p-4">
                    <div class="grid grid-cols-2 gap-6 text-sm">
                        <div><span class="font-semibold text-gray-700">Total Items:</span> <span id="transfer_total_items">0</span></div>
                        <div><span class="font-semibold text-gray-700">Total Qty:</span> <span id="transfer_total_qty">0.000</span></div>
                    </div>
                    <button type="submit" class="bg-gray-800 text-white rounded-lg px-6 py-2 font-semibold hover:bg-gray-900">Transfer Stock</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow mt-6 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-800">Recent Transfers</h3>
                <a href="{{ route('stores.transfer-report') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    View All Transfers <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">From</th>
                            <th class="px-4 py-3 text-left">To</th>
                            <th class="px-4 py-3 text-left">Product</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($transfers as $transfer)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $transfer->transfer_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">{{ $transfer->fromStore->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $transfer->toStore->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $transfer->items->count() }} Items</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $transfer->items->sum('quantity'), 3) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No transfers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Transfer Modal -->
        <div id="editTransferModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-gray-900/50 backdrop-blur-sm">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h3 class="text-lg font-bold text-gray-900">Edit Transfer</h3>
                        <button type="button" onclick="closeEditTransferModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
                    </div>
                    <form id="editTransferForm" method="POST" action="" class="p-6">
                        @csrf
                        @method('PUT')
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Quantity <span class="text-red-500">*</span></label>
                                <input type="number" step="0.001" min="0.001" name="quantity" id="edit_transfer_quantity" class="w-full border rounded-lg px-3 py-2" required>
                                <p class="text-xs text-gray-500 mt-1">Changing quantity will automatically adjust stock at both stores.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Transfer Date <span class="text-red-500">*</span></label>
                                <input type="date" name="transfer_date" id="edit_transfer_date" class="w-full border rounded-lg px-3 py-2" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Reference No</label>
                                <input type="text" name="reference_no" id="edit_transfer_reference" class="w-full border rounded-lg px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
                                <input type="text" name="notes" id="edit_transfer_notes" class="w-full border rounded-lg px-3 py-2">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Shipping Cost</label>
                                    <input type="number" step="0.01" min="0" name="shipping_cost" id="edit_transfer_shipping" class="w-full border rounded-lg px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Additional Exp.</label>
                                    <input type="number" step="0.01" min="0" name="additional_expense" id="edit_transfer_additional" class="w-full border rounded-lg px-3 py-2">
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" onclick="closeEditTransferModal()" class="rounded-lg border px-5 py-2 font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 font-semibold text-white hover:bg-blue-700">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($section === 'report')
        <div class="bg-white rounded-lg shadow overflow-hidden"><div class="p-5 border-b"><h3 class="font-semibold text-gray-800">Shipment Stock Report</h3></div><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3 text-left">GRN</th><th class="px-4 py-3 text-left">Supplier</th><th class="px-4 py-3 text-left">Product</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Landed Cost</th><th class="px-4 py-3 text-right">Allocated</th><th class="px-4 py-3 text-left">Status</th></tr></thead><tbody class="divide-y">@foreach($shipments as $shipment)@foreach($shipment->items as $item)<tr><td class="px-4 py-3">{{ $shipment->grn_no }}</td><td class="px-4 py-3">{{ $shipment->supplier->name ?? 'Manual' }}</td><td class="px-4 py-3">{{ $item->product->name }}</td><td class="px-4 py-3 text-right">{{ number_format($item->quantity, 3) }}</td><td class="px-4 py-3 text-right">{{ number_format($item->landed_unit_cost, 2) }}</td><td class="px-4 py-3 text-right">{{ number_format($item->allocations->sum('quantity'), 3) }}</td><td class="px-4 py-3">{{ ucfirst($shipment->status) }}</td></tr>@endforeach @endforeach</tbody></table></div></div>
    @endif
</div>

@if($section === 'transfers')
@php
    $transferProductsData = $products->map(function ($product) {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'stocks' => $product->storeStocks->mapWithKeys(fn ($stock) => [(string) $stock->store_id => (float) $stock->quantity]),
        ];
    })->values();
@endphp
@push('scripts')
<script>
    (function () {
        const products = @json($transferProductsData);

        const selectedProducts = new Map();
        let rowIndex = 0;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function selectedStoreId(role) {
            return document.getElementById(`transfer_${role}_store_id`)?.value || '';
        }

        function openStoreResults(role) {
            const results = document.getElementById(`transfer_${role}_store_results`);
            if (!results) return;

            results.classList.remove('hidden');
            filterStoreResults(role);
        }

        function hideStoreResults(role) {
            document.getElementById(`transfer_${role}_store_results`)?.classList.add('hidden');
        }

        function filterStoreResults(role) {
            const search = document.getElementById(`transfer_${role}_store_search`);
            const results = document.getElementById(`transfer_${role}_store_results`);
            const term = (search?.value || '').toLowerCase().trim();
            const oppositeRole = role === 'from' ? 'to' : 'from';
            const oppositeStoreId = selectedStoreId(oppositeRole);

            results?.classList.remove('hidden');
            results?.querySelectorAll('.transfer-store-option').forEach((option) => {
                const name = (option.dataset.storeName || '').toLowerCase();
                const isSelected = option.dataset.storeId === selectedStoreId(role);
                const isOpposite = option.dataset.storeId === oppositeStoreId;
                option.classList.toggle('hidden', isSelected || isOpposite || !name.includes(term));
            });
        }

        function setTransferStore(role, storeId, storeName) {
            document.getElementById(`transfer_${role}_store_id`).value = storeId;
            document.getElementById(`transfer_${role}_store_search`).value = '';
            document.getElementById(`transfer_${role}_store_chip`).innerHTML = `
                <button type="button" class="inline-flex items-center gap-2 rounded-full border border-yellow-200 bg-yellow-50 px-3 py-1 text-sm font-semibold text-amber-900" data-clear-store="${role}">
                    <span>${escapeHtml(storeName)}</span>
                    <i class="fas fa-times text-xs text-amber-700"></i>
                </button>
            `;

            hideStoreResults(role);
            filterStoreResults(role === 'from' ? 'to' : 'from');
            renderTransferRows();
            renderProductResults();
        }

        function clearTransferStore(role) {
            document.getElementById(`transfer_${role}_store_id`).value = '';
            document.getElementById(`transfer_${role}_store_chip`).innerHTML = '';
            filterStoreResults(role);
            filterStoreResults(role === 'from' ? 'to' : 'from');
            renderTransferRows();
            renderProductResults();
        }

        function productAvailable(product) {
            const fromStoreId = selectedStoreId('from');
            return fromStoreId ? Number(product.stocks?.[fromStoreId] || 0) : 0;
        }

        function renderProductResults() {
            const results = document.getElementById('transfer_product_results');
            const search = document.getElementById('transfer_product_search');
            if (!results || !search) return;

            const term = search.value.toLowerCase().trim();
            if (!document.activeElement?.isSameNode(search) && !term) return;

            const matches = products
                .filter((product) => !selectedProducts.has(String(product.id)))
                .filter((product) => {
                    const haystack = [product.name, product.sku, product.barcode].join(' ').toLowerCase();
                    return haystack.includes(term);
                })
                .slice(0, 20);

            results.innerHTML = matches.length
                ? matches.map((product) => {
                    const available = productAvailable(product);
                    return `
                        <button type="button" class="transfer-product-option flex w-full items-center justify-between rounded p-2 text-left hover:bg-green-50" data-product-id="${product.id}">
                            <span>
                                <span class="block font-semibold text-gray-800">${escapeHtml(product.name)}</span>
                                <span class="block text-xs text-gray-500">${escapeHtml(product.sku || product.barcode || 'No SKU')}</span>
                            </span>
                            <span class="text-xs font-semibold ${available > 0 ? 'text-green-700' : 'text-red-600'}">Available: ${available.toFixed(3)}</span>
                        </button>
                    `;
                }).join('')
                : '<div class="px-3 py-4 text-center text-sm text-gray-500">No products found.</div>';

            results.classList.remove('hidden');
        }

        function addProduct(productId) {
            const product = products.find((item) => String(item.id) === String(productId));
            if (!product || selectedProducts.has(String(product.id))) return;

            selectedProducts.set(String(product.id), {
                index: rowIndex++,
                id: product.id,
                name: product.name,
                sku: product.sku,
                quantity: Math.min(Math.max(productAvailable(product), 0), 1) || 1,
            });

            document.getElementById('transfer_product_search').value = '';
            document.getElementById('transfer_product_results').classList.add('hidden');
            renderTransferRows();
        }

        function removeProduct(productId) {
            selectedProducts.delete(String(productId));
            renderTransferRows();
            renderProductResults();
        }

        function renderTransferRows() {
            const body = document.getElementById('transferItemsBody');
            if (!body) return;

            if (!selectedProducts.size) {
                body.innerHTML = '<tr id="transferEmptyRow"><td colspan="5" class="px-3 py-8 text-center text-gray-500">Select products to transfer.</td></tr>';
                updateTotals();
                return;
            }

            body.innerHTML = Array.from(selectedProducts.values()).map((row, displayIndex) => {
                const product = products.find((item) => String(item.id) === String(row.id));
                const available = product ? productAvailable(product) : 0;
                const qty = Number(row.quantity || 0);
                const invalid = qty > available || qty <= 0;

                return `
                    <tr data-transfer-product-row="${row.id}">
                        <td class="px-3 py-3 font-semibold text-gray-600">${displayIndex + 1}</td>
                        <td class="px-3 py-3">
                            <div class="font-semibold text-gray-800">${escapeHtml(row.name)}</div>
                            <div class="text-xs text-gray-500">${escapeHtml(row.sku || 'No SKU')}</div>
                            <input type="hidden" name="items[${row.index}][product_id]" value="${row.id}">
                        </td>
                        <td class="px-3 py-3 text-right font-semibold ${available > 0 ? 'text-green-700' : 'text-red-600'}">${available.toFixed(3)}</td>
                        <td class="px-3 py-3 text-right">
                            <input type="number" min="0.001" step="any" name="items[${row.index}][quantity]" value="${qty}" data-transfer-qty="${row.id}" class="w-28 border rounded px-2 py-1 text-right ${invalid ? 'border-red-400 bg-red-50' : 'border-gray-300'}">
                        </td>
                        <td class="px-3 py-3 text-center">
                            <button type="button" class="text-red-600 hover:text-red-800" data-remove-product="${row.id}"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `;
            }).join('');

            updateTotals();
        }

        function updateTotals() {
            let totalQty = 0;
            selectedProducts.forEach((row) => {
                totalQty += Number(row.quantity || 0);
            });

            document.getElementById('transfer_total_items').textContent = selectedProducts.size;
            document.getElementById('transfer_total_qty').textContent = totalQty.toFixed(3);
        }

        document.querySelectorAll('[id^="transfer_"][id$="_store_search"]').forEach((input) => {
            const role = input.id.includes('_from_') ? 'from' : 'to';
            input.addEventListener('focus', () => openStoreResults(role));
            input.addEventListener('click', (event) => {
                event.stopPropagation();
                openStoreResults(role);
            });
            input.addEventListener('input', () => filterStoreResults(role));
        });

        document.querySelectorAll('.transfer-store-option').forEach((option) => {
            option.addEventListener('click', (event) => {
                event.stopPropagation();
                setTransferStore(option.dataset.role, option.dataset.storeId, option.dataset.storeName);
            });
        });

        document.addEventListener('click', (event) => {
            const clearButton = event.target.closest('[data-clear-store]');
            if (clearButton) {
                clearTransferStore(clearButton.dataset.clearStore);
                return;
            }

            if (!event.target.closest('.transfer-store-picker')) {
                hideStoreResults('from');
                hideStoreResults('to');
            }
        });

        const productSearch = document.getElementById('transfer_product_search');
        const productResults = document.getElementById('transfer_product_results');

        productSearch?.addEventListener('focus', renderProductResults);
        productSearch?.addEventListener('click', (event) => {
            event.stopPropagation();
            renderProductResults();
        });
        productSearch?.addEventListener('input', renderProductResults);

        productResults?.addEventListener('click', (event) => {
            event.stopPropagation();
            const option = event.target.closest('.transfer-product-option');
            if (!option) return;

            addProduct(option.dataset.productId);
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('#transfer_product_search') && !event.target.closest('#transfer_product_results')) {
                productResults?.classList.add('hidden');
            }
        });

        document.getElementById('transferItemsBody')?.addEventListener('input', (event) => {
            const input = event.target.closest('[data-transfer-qty]');
            if (!input) return;

            const row = selectedProducts.get(String(input.dataset.transferQty));
            if (!row) return;

            row.quantity = Number(input.value || 0);
            const product = products.find((item) => String(item.id) === String(row.id));
            const available = product ? productAvailable(product) : 0;
            const invalid = row.quantity <= 0 || row.quantity > available;
            input.classList.toggle('border-red-400', invalid);
            input.classList.toggle('bg-red-50', invalid);
            input.classList.toggle('border-gray-300', !invalid);
            updateTotals();
        });

        document.getElementById('transferItemsBody')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-product]');
            if (!button) return;

            removeProduct(button.dataset.removeProduct);
        });

        document.getElementById('transferForm')?.addEventListener('submit', (event) => {
            if (!selectedStoreId('from') || !selectedStoreId('to')) {
                event.preventDefault();
                alert('Select both From Store and To Store.');
                return;
            }

            if (selectedStoreId('from') === selectedStoreId('to')) {
                event.preventDefault();
                alert('From Store and To Store must be different.');
                return;
            }

            if (!selectedProducts.size) {
                event.preventDefault();
                alert('Select at least one product.');
                return;
            }

            for (const row of selectedProducts.values()) {
                const product = products.find((item) => String(item.id) === String(row.id));
                const available = product ? productAvailable(product) : 0;
                const qty = Number(row.quantity || 0);

                if (qty <= 0 || qty > available) {
                    event.preventDefault();
                    alert(`Check transfer quantity for ${row.name}.`);
                    return;
                }
            }
        });
    })();
</script>
@endpush
@endif

@if($section === 'overview')
@push('scripts')
<script>
    (function () {
        function formatDate(date) {
            const pad = (n) => n < 10 ? '0' + n : '' + n;
            return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
        }

        function goWithRange(start, end) {
            const url = new URL(window.location.href);
            url.searchParams.set('from', start);
            url.searchParams.set('to', end);
            window.location.href = url.toString();
        }

        const error = document.getElementById('store_custom_error');
        const customStart = document.getElementById('store_custom_start');
        const customEnd = document.getElementById('store_custom_end');

        document.querySelectorAll('[data-store-range]').forEach((button) => {
            button.addEventListener('click', () => {
                const range = button.dataset.storeRange;
                const end = new Date();
                let start = new Date(end.getFullYear(), end.getMonth(), end.getDate());

                if (range === 'yesterday') {
                    start.setDate(start.getDate() - 1);
                    goWithRange(formatDate(start), formatDate(start));
                    return;
                }

                if (range === 'this_week') {
                    const diff = (start.getDay() + 6) % 7;
                    start.setDate(start.getDate() - diff);
                    goWithRange(formatDate(start), formatDate(end));
                    return;
                }

                if (range === 'this_month') {
                    start = new Date(end.getFullYear(), end.getMonth(), 1);
                    goWithRange(formatDate(start), formatDate(end));
                    return;
                }

                if (range === 'custom') {
                    if (!customStart?.value || !customEnd?.value) {
                        error?.classList.remove('hidden');
                        return;
                    }
                    error?.classList.add('hidden');
                    goWithRange(customStart.value, customEnd.value);
                    return;
                }

                goWithRange(formatDate(end), formatDate(end));
            });
        });
    })();
</script>
@endpush
@endif
@endsection

@extends('layouts.app')

@section('title', 'Customers')
@section('page-title', 'Customer Management')

@section('content')
<div class="space-y-6">
    
    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Customer Management</h3>
            <p class="text-sm text-gray-600">Manage your customers and track their purchases</p>
        </div>
        <button 
            onclick="openCreateModal()" 
            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg"
        >
            <i class="fas fa-plus mr-2"></i>Add New Customer
        </button>
    </div>

    <!-- Customers Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Customer</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Contact</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Address</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Total Due</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($customers as $customer)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-green-600 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                    {{ strtoupper(substr($customer->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">{{ $customer->name }}</p>
                                    <p class="text-xs text-gray-500">ID: {{ $customer->id }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm">
                                <p class="text-gray-700"><i class="fas fa-phone text-blue-600 mr-1"></i> {{ $customer->phone ?? 'N/A' }}</p>
                                <p class="text-gray-500 text-xs"><i class="fas fa-envelope text-blue-600 mr-1"></i> {{ $customer->email ?? 'N/A' }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-700">{{ $customer->address ?? 'No address' }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php($position = \App\Models\Setting::get('currency_position', 'before'))
                            @php($decimals = (int) (\App\Models\Setting::get('decimal_places', 2)))
                            <span class="font-bold text-red-600">
                                @if($position === 'before')
                                    {{ $currency }} {{ number_format($customer->due_amount ?? 0, $decimals) }}
                                @else
                                    {{ number_format($customer->due_amount ?? 0, $decimals) }} {{ $currency }}
                                @endif
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($customer->is_active)
                                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Active</span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <button onclick="viewCustomer({{ $customer->id }})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="editCustomer({{ $customer->id }})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteCustomer({{ $customer->id }})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <i class="fas fa-user-tie text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 text-lg">No customers found</p>
                            <button onclick="openCreateModal()" class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-plus mr-2"></i>Add Customer
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Create Customer Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900">Add New Customer</h3>
            <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="createForm" onsubmit="submitCreate(event)">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">City</label>
                    <input type="text" name="city" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Country</label>
                    <input type="text" name="country" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Opening Balance</label>
                    <input type="number" name="opening_balance" step="0.01" value="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Address</label>
                    <textarea name="address" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" checked class="mr-2">
                        <span class="text-sm font-semibold text-gray-700">Active</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Create Customer</button>
            </div>
        </form>
    </div>
</div>

<!-- View Customer Modal -->
<div id="viewModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-6 border w-11/12 md:w-5/6 lg:w-4/5 shadow-lg rounded-lg bg-white max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6 pb-4 border-b">
            <h3 class="text-xl font-bold text-gray-900">Customer Details</h3>
            <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Customer Info Header -->
        <div id="customerHeader" class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-lg mb-4">
            <!-- Loaded via AJAX -->
        </div>
        
        <!-- Payment Reminder Action Bar -->
        <div id="reminderActionBar" class="mb-4 bg-white border rounded-lg p-3 flex justify-between items-center">
            <div class="text-sm text-gray-600">
                <i class="fas fa-info-circle text-blue-600 mr-1"></i>
                <span>Send payment reminders via SMS or WhatsApp</span>
            </div>
            <button onclick="openPaymentReminderModal(window.currentViewingCustomerId)" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition flex items-center gap-2">
                <i class="fas fa-paper-plane"></i>
                Send Reminder
            </button>
        </div>
        
        <!-- Tabs -->
        <div class="mb-4">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px space-x-4">
                    <button onclick="switchTab('ledger')" id="tab-ledger" class="tab-button active border-b-2 border-blue-600 py-3 px-4 text-sm font-semibold text-blue-600">
                        <i class="fas fa-book mr-2"></i>Ledger
                    </button>
                    <button onclick="switchTab('sales')" id="tab-sales" class="tab-button border-b-2 border-transparent py-3 px-4 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-shopping-cart mr-2"></i>Sales
                    </button>
                    <button onclick="switchTab('payments')" id="tab-payments" class="tab-button border-b-2 border-transparent py-3 px-4 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-money-bill-wave mr-2"></i>Payments
                    </button>
                </nav>
            </div>
        </div>
        
        <!-- Tab Content -->
        <div id="viewContent">
            <!-- Ledger Tab -->
            <div id="content-ledger" class="tab-content">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                    <!-- Account Summary Card -->
                    <div class="lg:col-span-1 bg-white border rounded-lg p-4 shadow-sm">
                        <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-chart-line text-blue-600 mr-2"></i>Account Summary
                        </h4>
                        <div id="accountSummary" class="space-y-3">
                            <!-- Loaded via AJAX -->
                        </div>
                    </div>
                    
                    <!-- Ledger Table -->
                    <div class="lg:col-span-2">
                        <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-blue-600 text-white">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Date</th>
                                            <th class="px-4 py-3 text-left">Reference No</th>
                                            <th class="px-4 py-3 text-left">Type</th>
                                            <th class="px-4 py-3 text-right">Debit</th>
                                            <th class="px-4 py-3 text-right">Credit</th>
                                            <th class="px-4 py-3 text-center">Payment Method</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ledgerTable" class="divide-y divide-gray-200">
                                        <!-- Loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sales Tab -->
            <div id="content-sales" class="tab-content hidden">
                <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-3 text-left">Invoice No</th>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3 text-right">Paid</th>
                                    <th class="px-4 py-3 text-right">Due</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="salesTable" class="divide-y divide-gray-200">
                                <!-- Loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Payments Tab -->
            <div id="content-payments" class="tab-content hidden">
                <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-left">Reference</th>
                                    <th class="px-4 py-3 text-left">Invoice</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3 text-center">Method</th>
                                    <th class="px-4 py-3 text-left">Note</th>
                                </tr>
                            </thead>
                            <tbody id="paymentsTable" class="divide-y divide-gray-200">
                                <!-- Loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900">Edit Customer</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="editForm" onsubmit="submitEdit(event)">
            @csrf
            @method('PUT')
            <input type="hidden" id="edit_customer_id" name="customer_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" id="edit_name" name="name" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Phone</label>
                    <input type="text" id="edit_phone" name="phone" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <input type="email" id="edit_email" name="email" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">City</label>
                    <input type="text" id="edit_city" name="city" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Country</label>
                    <input type="text" id="edit_country" name="country" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Opening Balance</label>
                    <input type="number" id="edit_opening_balance" name="opening_balance" step="0.01" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Address</label>
                    <textarea id="edit_address" name="address" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1" class="mr-2">
                        <span class="text-sm font-semibold text-gray-700">Active</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update Customer</button>
            </div>
        </form>
    </div>
</div>

<script>
const CURRENCY = @json($currency . ' ');
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
    document.getElementById('createForm').reset();
}

function submitCreate(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    fetch('{{ route("customers.store") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeCreateModal();
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to create customer');
    });
}

function viewCustomer(id) {
    const BASE_URL = '{{ url('/') }}';
    fetch(`${BASE_URL}/customers/${id}`, { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        const { customer, period_totals, overall_totals, transactions, start_date, end_date } = data;

        renderCustomerHeader(customer);
        renderAccountSummary(period_totals, overall_totals, start_date, end_date);
        renderLedger(transactions);
        renderSales(transactions);
        renderPayments(transactions);

        // Default to Ledger tab
        switchTab('ledger');
        document.getElementById('viewModal').classList.remove('hidden');
    })
    .catch(err => {
        console.error(err);
        alert('Failed to load customer details');
    });
}

function renderCustomerHeader(c) {
    window.currentViewingCustomerId = c.id;
    const el = document.getElementById('customerHeader');
    el.innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-sm">
            <div><span class="font-semibold text-gray-700">Name</span><div>${c.name}</div></div>
            <div><span class="font-semibold text-gray-700">Phone</span><div>${c.phone || 'N/A'}</div></div>
            <div><span class="font-semibold text-gray-700">Email</span><div>${c.email || 'N/A'}</div></div>
            <div><span class="font-semibold text-gray-700">City</span><div>${c.city || 'N/A'}</div></div>
            <div><span class="font-semibold text-gray-700">Country</span><div>${c.country || 'N/A'}</div></div>
            <div><span class="font-semibold text-gray-700">Opening Balance</span><div>${CURRENCY}${Number(c.opening_balance||0).toFixed(2)}</div></div>
            <div class="sm:col-span-2 md:col-span-3"><span class="font-semibold text-gray-700">Address</span><div>${c.address || 'No address'}</div></div>
            <div><span class="font-semibold text-gray-700">Status</span><div>${c.is_active ? '<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Active</span>' : '<span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">Inactive</span>'}</div></div>
        </div>
    `;
}

function renderAccountSummary(periodTotals, overallTotals, start, end) {
    const wrap = document.getElementById('accountSummary');
    wrap.innerHTML = `
        <div class="text-xs text-gray-600 flex items-center gap-2"><i class="fas fa-info-circle text-blue-500"></i>${formatDate(start)} To ${formatDate(end)}</div>
        <div class="flex justify-between text-sm"><span class="text-gray-600">Total invoice</span><span class="font-semibold">${CURRENCY}${fmt(periodTotals.invoice)}</span></div>
        <div class="flex justify-between text-sm"><span class="text-gray-600">Total paid</span><span class="font-semibold">${CURRENCY}${fmt(periodTotals.paid)}</span></div>
        <div class="border-t pt-2"></div>
        <div class="text-sm text-gray-600">Overall Summary</div>
        <div class="flex justify-between text-sm"><span class="text-gray-600">Total invoice</span><span class="font-semibold">${CURRENCY}${fmt(overallTotals.invoice)}</span></div>
        <div class="flex justify-between text-sm"><span class="text-gray-600">Total paid</span><span class="font-semibold">${CURRENCY}${fmt(overallTotals.paid)}</span></div>
        <div class="flex justify-between text-sm"><span class="text-gray-700 font-semibold">Balance due</span><span class="font-bold ${Number(overallTotals.balance)>0?'text-red-600':'text-green-600'}">${CURRENCY}${fmt(overallTotals.balance)}</span></div>
    `;
}

function renderLedger(trans) {
    const tbody = document.getElementById('ledgerTable');
    tbody.innerHTML = (trans || []).map(t => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2">${formatDate(t.date)}</td>
            <td class="px-4 py-2">${t.reference}</td>
            <td class="px-4 py-2">${t.type}</td>
            <td class="px-4 py-2">${t.location || ''}</td>
            <td class="px-4 py-2 text-center">${badge(t.payment_status)}</td>
            <td class="px-4 py-2 text-right">${t.debit? CURRENCY+fmt(t.debit): '—'}</td>
            <td class="px-4 py-2 text-right">${t.credit? CURRENCY+fmt(t.credit): '—'}</td>
            <td class="px-4 py-2 text-center">${t.payment_method || '—'}</td>
            <td class="px-4 py-2">${t.notes || '—'}</td>
        </tr>
    `).join('');
}

function renderSales(trans) {
    const sales = (trans || []).filter(t => t.type === 'Sell');
    const tbody = document.getElementById('salesTable');
    tbody.innerHTML = sales.map(s => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2">${s.invoice}</td>
            <td class="px-4 py-2">${formatDate(s.sale_date || s.date)}</td>
            <td class="px-4 py-2 text-right">${CURRENCY}${fmt(s.debit)}</td>
            <td class="px-4 py-2 text-right">${CURRENCY}${fmt(s.paid || 0)}</td>
            <td class="px-4 py-2 text-right">${CURRENCY}${fmt(s.due || (s.debit - (s.paid||0)))}</td>
            <td class="px-4 py-2 text-center">${badge(s.payment_status)}</td>
            <td class="px-4 py-2 text-center">
                ${s.sale_id ? `<a class="text-blue-600 hover:underline" href="${BASE_URL}/sales/${s.sale_id}" target="_blank">View</a>` : ''}
            </td>
        </tr>
    `).join('');
}

function renderPayments(trans) {
    const pays = (trans || []).filter(t => t.type === 'Payment');
    const tbody = document.getElementById('paymentsTable');
    tbody.innerHTML = pays.map(p => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2">${formatDate(p.date)}</td>
            <td class="px-4 py-2">${p.reference}</td>
            <td class="px-4 py-2">${p.invoice}</td>
            <td class="px-4 py-2 text-right">${CURRENCY}${fmt(p.credit)}</td>
            <td class="px-4 py-2 text-center">${p.payment_method || '—'}</td>
            <td class="px-4 py-2">${p.notes || '—'}</td>
        </tr>
    `).join('');
}

function switchTab(tab) {
    const tabs = ['ledger','sales','payments'];
    tabs.forEach(t => {
        document.getElementById(`content-${t}`).classList.toggle('hidden', t !== tab);
        document.getElementById(`tab-${t}`).classList.toggle('text-blue-600', t === tab);
        document.getElementById(`tab-${t}`).classList.toggle('border-blue-600', t === tab);
        document.getElementById(`tab-${t}`).classList.toggle('text-gray-500', t !== tab);
        document.getElementById(`tab-${t}`).classList.toggle('border-transparent', t !== tab);
    });
}

function badge(status) {
    const s = (status||'').toLowerCase();
    if (s === 'paid') return '<span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">Paid</span>';
    if (s === 'partial') return '<span class="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-700">Partial</span>';
    return '<span class="px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700">Pending</span>';
}

const fmt = n => Number(n||0).toFixed(2);
const formatDate = d => {
    if (!d) return '';
    const dt = new Date(d);
    if (isNaN(dt.getTime())) return d;
    return dt.toLocaleDateString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}

function editCustomer(id) {
    const BASE_URL = '{{ url('/') }}';
    fetch(`${BASE_URL}/customers/${id}/edit`, {
        headers: {
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const customer = data.customer;
            document.getElementById('edit_customer_id').value = customer.id;
            document.getElementById('edit_name').value = customer.name;
            document.getElementById('edit_phone').value = customer.phone || '';
            document.getElementById('edit_email').value = customer.email || '';
            document.getElementById('edit_city').value = customer.city || '';
            document.getElementById('edit_country').value = customer.country || '';
            document.getElementById('edit_opening_balance').value = customer.opening_balance || 0;
            document.getElementById('edit_address').value = customer.address || '';
            document.getElementById('edit_is_active').checked = customer.is_active;
            document.getElementById('editModal').classList.remove('hidden');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to load customer data');
    });
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editForm').reset();
}

function submitEdit(event) {
    event.preventDefault();
    const form = event.target;
    const customerId = document.getElementById('edit_customer_id').value;
    const formData = new FormData(form);
    
    const BASE_URL = '{{ url('/') }}';
    fetch(`${BASE_URL}/customers/${customerId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEditModal();
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update customer');
    });
}

function deleteCustomer(id) {
    if (!confirm('Are you sure you want to delete this customer? This action cannot be undone.')) {
        return;
    }
    
    const BASE_URL = '{{ url('/') }}';
    fetch(`${BASE_URL}/customers/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to delete customer');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete customer');
    });
}
</script>

@include('customers.payment-reminder-modal')


@include('customers.payment-reminder-modal')

@endsection

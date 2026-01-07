@extends('layouts.app')

@section('title', 'Send Promotion')
@section('page-title', 'Send Promotion')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-bullhorn text-indigo-600 mr-2"></i>Send Promotion Message
        </h3>
        @if(session('success'))
            <div class="mb-4 p-3 rounded bg-green-100 text-green-700 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('whatsapp_links'))
            <div class="mb-4 p-4 rounded bg-blue-50 border border-blue-200">
                <p class="font-semibold text-sm mb-2">WhatsApp Links Generated - Click to send:</p>
                <div class="space-y-1 max-h-48 overflow-y-auto">
                    @foreach(session('whatsapp_links') as $idx => $link)
                        <a href="{{ $link }}" target="_blank" class="text-xs text-blue-600 hover:underline block">
                            <i class="fab fa-whatsapp mr-1"></i>Customer {{ $idx + 1 }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
        <form method="post" action="{{ route('notifications.promotion.send') }}" class="space-y-4" id="promotionForm">
            @csrf
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-600">Channel</label>
                    <select name="channel" class="mt-1 w-full border rounded px-3 py-2 text-sm" required>
                        <option value="sms">SMS</option>
                        @if(env('ENABLE_WHATSAPP', false))
                        <option value="whatsapp">WhatsApp</option>
                        <option value="both">Both</option>
                        @endif
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-600">Customers</label>
                    <select name="customer_scope" id="customerScope" class="mt-1 w-full border rounded px-3 py-2 text-sm" onchange="toggleCustomerSelection()">
                        <option value="all">All Customers</option>
                        <option value="active">Active Only</option>
                        <option value="selected">Select Specific</option>
                        <option value="top5">Top 5 Spenders</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm w-full">Send Promotion</button>
                </div>
            </div>
            
            <div id="customerSelectionPanel" class="hidden border rounded p-4 bg-gray-50">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-medium text-gray-700">Select Customers:</p>
                    <input type="text" id="customerSearch" placeholder="Search customers..." class="text-xs border rounded px-2 py-1 w-48" onkeyup="filterCustomers()">
                </div>
                <div id="customerList" class="max-h-64 overflow-y-auto space-y-2">
                    @foreach($customers as $c)
                        <label class="flex items-center text-sm customer-item" data-name="{{ strtolower($c->name) }}" data-phone="{{ $c->phone }}">
                            <input type="checkbox" name="customer_ids[]" value="{{ $c->id }}" class="mr-2">
                            <span class="{{ $c->is_active ? 'text-gray-800' : 'text-gray-400' }}">
                                {{ $c->name }} 
                                @if($c->phone)
                                    <span class="text-xs text-gray-500">({{ $c->phone }})</span>
                                @endif
                                @if(!$c->is_active)
                                    <span class="text-xs text-red-500">[Inactive]</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Top 5 Spenders preview -->
            <div id="top5Preview" class="hidden border rounded p-4 bg-gradient-to-br from-indigo-50 to-white">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-semibold text-indigo-700">Top 5 Customers by Spend</p>
                    <span class="text-xs text-gray-500">Auto-selected when sending</span>
                </div>
                <div class="grid md:grid-cols-5 sm:grid-cols-2 gap-3">
                    @foreach(($topCustomers ?? []) as $t)
                        <div class="rounded-xl border border-indigo-100 bg-white shadow-sm p-3 flex items-center gap-3">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center text-sm font-semibold">
                                {{ strtoupper(substr($t->name,0,1)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-gray-800">{{ $t->name }}</div>
                                <div class="text-xs text-gray-500 truncate">{{ $t->phone }}</div>
                                <div class="text-xs text-indigo-600 font-semibold">{{ number_format($t->total_spend ?? 0, 2) }}</div>
                            </div>
                        </div>
                    @endforeach
                    @if(($topCustomers ?? collect())->isEmpty())
                        <div class="text-sm text-gray-500">No spending data yet.</div>
                    @endif
                </div>
            </div>
            
            <div>
                <label class="text-sm font-medium text-gray-600">Message</label>
                <textarea name="message" rows="4" class="mt-1 w-full border rounded px-3 py-2 text-sm" placeholder="Enter promotion details" required>{{ old('message') }}</textarea>
            </div>
            @if($errors->any())
                <ul class="text-sm text-red-600 list-disc pl-5">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            @endif
        </form>
        @if(env('ENABLE_WHATSAPP', false))
        <p class="text-xs text-gray-500 mt-4">Note: WhatsApp opens web links. SMS is stubbed (integrate provider for real sending).</p>
        @else
        <p class="text-xs text-gray-500 mt-4">Note: SMS integration is active. To enable WhatsApp, set ENABLE_WHATSAPP=true in your .env file.</p>
        @endif
    </div>
</div>

<script>
function toggleCustomerSelection() {
    const scope = document.getElementById('customerScope').value;
    const panel = document.getElementById('customerSelectionPanel');
    const top5 = document.getElementById('top5Preview');
    if (scope === 'selected') {
        panel.classList.remove('hidden');
        top5.classList.add('hidden');
    } else {
        panel.classList.add('hidden');
        if (scope === 'top5') {
            top5.classList.remove('hidden');
        } else {
            top5.classList.add('hidden');
        }
    }
}

function filterCustomers() {
    const searchTerm = document.getElementById('customerSearch').value.toLowerCase();
    const customers = document.querySelectorAll('.customer-item');
    
    customers.forEach(item => {
        const name = item.getAttribute('data-name');
        const phone = item.getAttribute('data-phone') || '';
        
        if (name.includes(searchTerm) || phone.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>
@endsection

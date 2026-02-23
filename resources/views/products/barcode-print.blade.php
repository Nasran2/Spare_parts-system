@extends('layouts.app')

@section('title', 'Barcode Print')
@section('page-title', 'Barcode Print')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Barcode Print</h3>
            <p class="text-sm text-gray-600">Select products and quantities to print labels. Uses your default barcode settings.</p>
        </div>
        <a href="{{ route('settings.barcode') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
            <i class="fas fa-cog mr-2"></i>Barcode Settings
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="mb-4 flex flex-col md:flex-row md:items-center gap-3">
            <div class="flex-1">
                <input type="text" id="barcodeSearch" placeholder="Search by product name or barcode..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div id="searchResults" class="border border-gray-200 rounded-lg p-4 text-sm text-gray-500">
            Start typing to search products.
        </div>

        <form method="POST" action="{{ route('products.barcode.preview') }}" target="_blank" class="mt-6">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Product</th>
                            <th class="px-4 py-3 text-left">Barcode</th>
                            <th class="px-4 py-3 text-left">Price</th>
                            @if(($settings['barcode_enable_selling_secret_code'] ?? false))
                                <th class="px-4 py-3 text-left">Secret Price</th>
                            @endif
                            <th class="px-4 py-3 text-left">Qty</th>
                            <th class="px-4 py-3 text-left">Remove</th>
                        </tr>
                    </thead>
                    <tbody id="selectedRows" class="divide-y">
                        <tr>
                            <td colspan="{{ ($settings['barcode_enable_selling_secret_code'] ?? false) ? 6 : 5 }}" class="px-4 py-6 text-center text-gray-500">No products selected.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-lg hover:from-indigo-700 hover:to-indigo-800 transition shadow-lg">
                    <i class="fas fa-print mr-2"></i>Print Barcodes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const searchInput = document.getElementById('barcodeSearch');
const searchResults = document.getElementById('searchResults');
const selectedRows = document.getElementById('selectedRows');
const selectedMap = new Map();
const SELLING_SECRET_ENABLED = {{ ($settings['barcode_enable_selling_secret_code'] ?? false) ? 'true' : 'false' }};
const EMPTY_COLSPAN = SELLING_SECRET_ENABLED ? 6 : 5;

function formatPrice(value) {
    const num = Number(value || 0);
    return num.toFixed(2);
}

function renderSelected() {
    if (selectedMap.size === 0) {
        selectedRows.innerHTML = `<tr><td colspan="${EMPTY_COLSPAN}" class="px-4 py-6 text-center text-gray-500">No products selected.</td></tr>`;
        return;
    }
    selectedRows.innerHTML = '';
    selectedMap.forEach((item) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-4 py-3 font-medium text-gray-800">
                ${item.name}
                <input type="hidden" name="products[]" value="${item.id}">
            </td>
            <td class="px-4 py-3 text-gray-600">${item.barcode || ''}</td>
            <td class="px-4 py-3 text-gray-600">${formatPrice(item.selling_price)}</td>
            ${SELLING_SECRET_ENABLED ? `
            <td class="px-4 py-3">
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="show_secret_price[${item.id}]" value="0">
                    <input type="checkbox" name="show_secret_price[${item.id}]" value="1" ${item.show_secret_price ? 'checked' : ''} class="rounded text-indigo-600 border-gray-300">
                    <span class="text-xs text-gray-600">ON</span>
                </label>
            </td>` : ''}
            <td class="px-4 py-3">
                <input type="number" name="qty[${item.id}]" min="1" value="${item.qty}" class="w-20 px-3 py-1 border border-gray-300 rounded-lg">
            </td>
            <td class="px-4 py-3">
                <button type="button" data-remove="${item.id}" class="text-red-600 hover:text-red-800">Remove</button>
            </td>
        `;
        selectedRows.appendChild(row);
    });
}

function renderResults(items) {
    if (!items.length) {
        searchResults.innerHTML = '<div class="text-gray-500">No products found.</div>';
        return;
    }
    searchResults.innerHTML = '';
    const list = document.createElement('div');
    list.className = 'space-y-2';
    items.forEach((item) => {
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between p-3 border border-gray-200 rounded-lg';
        row.innerHTML = `
            <div>
                <div class="font-semibold text-gray-800">${item.name}</div>
                <div class="text-xs text-gray-500">${item.barcode || ''} • ${formatPrice(item.selling_price)}</div>
            </div>
            <button type="button" data-add="${item.id}" class="px-3 py-1 bg-indigo-600 text-white rounded-lg text-xs">Add</button>
        `;
        list.appendChild(row);
    });
    searchResults.appendChild(list);
}

async function searchProducts(term) {
    if (!term) {
        searchResults.textContent = 'Start typing to search products.';
        return;
    }
    const res = await fetch(`{{ route('products.barcode.search') }}?term=${encodeURIComponent(term)}`);
    const data = await res.json();
    renderResults(data);
}

searchInput?.addEventListener('input', (event) => {
    const term = event.target.value.trim();
    clearTimeout(searchInput._timer);
    searchInput._timer = setTimeout(() => searchProducts(term), 300);
});

searchResults?.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-add]');
    if (!btn) return;
    const id = Number(btn.getAttribute('data-add'));
    fetch(`{{ route('products.barcode.search') }}?term=${encodeURIComponent(searchInput.value.trim())}`)
        .then(res => res.json())
        .then(items => {
            const item = items.find(p => p.id === id);
            if (!item) return;
            const existing = selectedMap.get(id) || { qty: 0, show_secret_price: false };
            selectedMap.set(id, { ...item, qty: existing.qty + 1, show_secret_price: !!existing.show_secret_price });
            renderSelected();
        });
});

selectedRows?.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-remove]');
    if (!btn) return;
    const id = Number(btn.getAttribute('data-remove'));
    selectedMap.delete(id);
    renderSelected();
});

selectedRows?.addEventListener('input', (event) => {
    const row = event.target.closest('tr');
    if (!row) return;
    const hidden = row.querySelector('input[name="products[]"]');
    if (!hidden) return;

    const id = Number(hidden.value);
    const current = selectedMap.get(id);
    if (!current) return;

    if (event.target.matches('input[name^="qty["]')) {
        const qty = Math.max(1, Number(event.target.value || 1));
        selectedMap.set(id, { ...current, qty });
    }

    if (event.target.matches('input[name^="show_secret_price["][type="checkbox"]')) {
        selectedMap.set(id, { ...current, show_secret_price: !!event.target.checked });
    }
});
</script>
@endsection

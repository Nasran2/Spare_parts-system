@extends('layouts.app')

@section('title', 'Brands')
@section('page-title', 'Brands')

@section('content')
@php
    $controls = is_array($controls ?? null) ? $controls : [];
    $priceVisiblePct = (float) ($controls['price_visible_percentage'] ?? 100);
    $applyPct = function ($value, $pct) {
        $pct = max(0, min(100, (float) $pct));
        return (float) $value * ($pct / 100);
    };
    $maskMoney = function ($value, $forceHide = false) use ($controls, $priceVisiblePct, $applyPct) {
        if ($forceHide || !empty($controls['hide_price_wise_data'])) {
            return '—';
        }

        $masked = $applyPct((float) $value, $priceVisiblePct);

        $roundToWhole = $priceVisiblePct < 100;


        return number_format($roundToWhole ? round($masked) : $masked, $roundToWhole ? 0 : 2);
    };
@endphp
<div class="space-y-6">
    
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Brand Management</h3>
            <p class="text-sm text-gray-600">Manage your product brands</p>
        </div>
        <button 
            onclick="openCreateModal()" 
            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg"
        >
            <i class="fas fa-plus mr-2"></i>Add New Brand
        </button>
    </div>

    <div>
        <label class="text-xs font-semibold text-gray-600">Quick search</label>
        <input type="search" id="brandSearchInput" placeholder="Search brands" class="mt-2 w-full px-4 py-2 border rounded-lg focus:border-blue-500 focus:ring focus:ring-blue-100">
    </div>

    <!-- Brands Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @if(isset($brands) && $brands->count())
            @foreach($brands as $brand)
                <div data-brand-card class="bg-white rounded-lg shadow p-4 flex flex-col justify-between" data-search-text="{{ strtolower($brand->name . ' ' . ($brand->description ?? '')) }}">
                    <div>
                        <div class="flex justify-between items-start">
                            <h4 class="text-lg font-semibold text-gray-800">{{ $brand->name }}</h4>
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-full">
                                {{ $brand->products_count }} Products
                            </span>
                        </div>
                        @if($brand->description)
                            <p class="text-sm text-gray-500 mt-2">{{ Str::limit($brand->description, 120) }}</p>
                        @endif
                        <div class="mt-3 space-y-1 text-xs text-gray-600">
                            <p>Total Cost: <span class="font-semibold text-gray-800">{{ $currency }}{{ $maskMoney((float) ($brand->total_cost_price ?? 0), !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</span></p>
                            <p>Total Selling: <span class="font-semibold text-gray-800">{{ $currency }}{{ $maskMoney((float) ($brand->total_selling_price ?? 0), !empty($controls['hide_actual_stock_price'])) }}</span></p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-gray-500">Created: {{ $brand->created_at->format('Y-m-d') }}</div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('products.index', ['brand_id' => $brand->id]) }}" class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm" title="View Products">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('brands.edit', $brand->id) }}" class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">Edit</a>
                            <form action="{{ route('brands.destroy', $brand->id) }}" method="POST" onsubmit="return confirm('Delete this brand?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600 text-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="col-span-full text-center py-12">
                <i class="fas fa-certificate text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg mb-2">No brands yet</p>
                <p class="text-gray-400 text-sm mb-4">Create your first product brand</p>
                <button 
                    onclick="openCreateModal()"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                >
                    <i class="fas fa-plus mr-2"></i>Add Brand
                </button>
            </div>
        @endif
    </div>

</div>

<!-- Create Brand Modal -->
<div id="createBrandModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white w-full max-w-md rounded-xl shadow-xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Add New Brand</h3>
            <button onclick="closeCreateModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <form id="createBrandForm" action="{{ route('brands.store') }}" method="POST">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Name *</label>
                <input type="text" name="name" required class="w-full px-4 py-2 border rounded" />
                <p id="nameError" class="hidden text-red-500 text-xs mt-1"></p>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea name="description" class="w-full px-4 py-2 border rounded"></textarea>
            </div>

            <div class="mt-6 flex items-center justify-end gap-2">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 text-gray-600">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save Brand</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createBrandModal').classList.remove('hidden');
    document.getElementById('createBrandModal').classList.add('flex');
}
function closeCreateModal() {
    document.getElementById('createBrandModal').classList.add('hidden');
    document.getElementById('createBrandModal').classList.remove('flex');
}

document.getElementById('createBrandForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const url = form.getAttribute('action');
    const formData = new FormData(form);

    // Clear errors
    const nameError = document.getElementById('nameError');
    nameError.classList.add('hidden');
    nameError.textContent = '';


    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });

        if (resp.status === 422) {
            const data = await resp.json();
            if (data.errors && data.errors.name) {
                nameError.textContent = data.errors.name[0];
                nameError.classList.remove('hidden');
            }
            return;
        }

        const data = await resp.json();
        if (data.success) {
            // Append new brand card to the grid
            const grid = document.querySelector('.grid');
            const card = document.createElement('div');
            card.setAttribute('data-brand-card', '');
            card.setAttribute('data-search-text', (data.brand.name + ' ' + (data.brand.description || '')).toLowerCase());
            card.className = 'bg-white rounded-lg shadow p-4 flex flex-col justify-between';
            card.innerHTML = `
                <div>
                    <div class="flex justify-between items-start">
                        <h4 class="text-lg font-semibold text-gray-800">${data.brand.name}</h4>
                        <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-full">
                            0 Products
                        </span>
                    </div>
                    ${data.brand.description ? `<p class="text-sm text-gray-500 mt-2">${data.brand.description}</p>` : ''}
                    <div class="mt-3 space-y-1 text-xs text-gray-600">
                        <p>Total Cost: <span class="font-semibold text-gray-800">{{ $currency }}0.00</span></p>
                        <p>Total Selling: <span class="font-semibold text-gray-800">{{ $currency }}0.00</span></p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <div class="text-sm text-gray-500">Created: ${new Date(data.brand.created_at).toISOString().slice(0,10)}</div>
                    <div class="flex items-center gap-2">
                        <a href="{{ url('products') }}?brand_id=${data.brand.id}" class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm" title="View Products">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="${`{{ url('brands') }}`}/${data.brand.id}/edit" class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">Edit</a>
                        <form action="${`{{ url('brands') }}`}/${data.brand.id}" method="POST" onsubmit="return confirm('Delete this brand?');">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <input type="hidden" name="_method" value="DELETE" />
                            <button type="submit" class="px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600 text-sm">Delete</button>
                        </form>
                    </div>
                </div>
            `;
            grid.prepend(card);

            // Close and reset form
            form.reset();
            closeCreateModal();
        }
    } catch (err) {
        alert('Failed to create brand. Please try again.');
        console.error(err);
    }
});

const brandSearchInput = document.getElementById('brandSearchInput');
const brandCards = document.querySelectorAll('[data-brand-card]');
brandSearchInput?.addEventListener('input', function () {
    const term = this.value.trim().toLowerCase();
    brandCards.forEach(card => {
        const text = card.dataset.searchText || '';
        card.style.display = text.includes(term) ? '' : 'none';
    });
});
</script>
@endsection

@extends('layouts.app')

@section('title', 'Categories')
@section('page-title', 'Categories')

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
            <h3 class="text-lg font-semibold text-gray-800">Category Management</h3>
            <p class="text-sm text-gray-600">Manage your product categories</p>
        </div>
        <button 
            onclick="openCreateModal()" 
            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg"
        >
            <i class="fas fa-plus mr-2"></i>Add New Category
        </button>
    </div>

    <div>
        <label class="text-xs font-semibold text-gray-600">Quick search</label>
        <input id="categorySearchInput" type="search" placeholder="Search categories" class="mt-2 w-full px-4 py-2 border rounded-lg focus:border-blue-500 focus:ring focus:ring-blue-100">
    </div>

    @if(isset($categories) && $categories->count())
        @php
            $byId = ($categories ?? collect())->keyBy('id');
            $mainCategories = ($categories ?? collect())
                ->filter(fn($c) => $c->parent_id === null || !$byId->has($c->parent_id))
                ->sortBy('name');
            $subCategories = ($categories ?? collect())
                ->filter(fn($c) => $c->parent_id !== null && $byId->has($c->parent_id))
                ->sortBy('name');
        @endphp

        <div class="space-y-6">
            <div>
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Main Categories</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($mainCategories as $category)
                        @php
                            $searchText = strtolower(trim($category->name . ' ' . ($category->description ?? '') . ' main category'));
                        @endphp
                        <div data-category-card class="bg-white rounded-lg shadow p-4 flex flex-col justify-between" data-search-text="{{ $searchText }}">
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="text-lg font-semibold text-gray-800">{{ $category->name }}</h4>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-full whitespace-nowrap">
                                        {{ (int) ($category->products_count ?? 0) }} Products
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Main Category</p>
                                @if($category->description)
                                    <p class="text-sm text-gray-500 mt-2">{{ Str::limit($category->description, 120) }}</p>
                                @endif
                                <div class="mt-3 space-y-1 text-xs text-gray-600">
                                    <p>Total Cost: <span class="font-semibold text-gray-800">{{ $currency }}{{ $maskMoney((float) ($category->total_cost_price ?? 0), !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</span></p>
                                    <p>Total Selling: <span class="font-semibold text-gray-800">{{ $currency }}{{ $maskMoney((float) ($category->total_selling_price ?? 0), !empty($controls['hide_actual_stock_price'])) }}</span></p>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between">
                                <div class="text-sm text-gray-500">Created: {{ $category->created_at->format('Y-m-d') }}</div>
                                <div class="flex items-center gap-2 flex-wrap justify-end">
                                    <a href="{{ route('products.index', ['category_id' => $category->id]) }}" class="px-3 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">View</a>
                                    <a href="{{ route('categories.edit', $category->id) }}" class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">Edit</a>
                                    <form action="{{ route('categories.destroy', $category->id) }}" method="POST" onsubmit="return confirm('Delete this category?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600 text-sm">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Sub Categories</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @forelse($subCategories as $category)
                        @php
                            $parentName = optional($byId->get($category->parent_id))->name;
                            $searchText = strtolower(trim($category->name . ' ' . ($category->description ?? '') . ' ' . ($parentName ?? '') . ' sub category'));
                        @endphp
                        <div data-category-card class="bg-gray-50 border border-gray-200 rounded-lg p-4 flex flex-col justify-between" data-search-text="{{ $searchText }}">
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="text-lg font-semibold text-gray-800">{{ $category->name }}</h4>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-full whitespace-nowrap">
                                        {{ (int) ($category->products_count ?? 0) }} Products
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Sub Category</p>
                                @if($parentName)
                                    <p class="text-xs text-gray-500 mt-1">Parent: {{ $parentName }}</p>
                                @endif
                                @if($category->description)
                                    <p class="text-sm text-gray-500 mt-2">{{ Str::limit($category->description, 120) }}</p>
                                @endif
                                <div class="mt-3 space-y-1 text-xs text-gray-600">
                                    <p>Total Cost: <span class="font-semibold text-gray-800">{{ $currency }}{{ $maskMoney((float) ($category->total_cost_price ?? 0), !empty($controls['hide_actual_purchase_price']) || !empty($controls['hide_actual_stock_price'])) }}</span></p>
                                    <p>Total Selling: <span class="font-semibold text-gray-800">{{ $currency }}{{ $maskMoney((float) ($category->total_selling_price ?? 0), !empty($controls['hide_actual_stock_price'])) }}</span></p>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between">
                                <div class="text-sm text-gray-500">Created: {{ $category->created_at->format('Y-m-d') }}</div>
                                <div class="flex items-center gap-2 flex-wrap justify-end">
                                    <a href="{{ route('products.index', ['category_id' => $category->parent_id, 'subcategory_id' => $category->id]) }}" class="px-3 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">View</a>
                                    <a href="{{ route('categories.edit', $category->id) }}" class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">Edit</a>
                                    <form action="{{ route('categories.destroy', $category->id) }}" method="POST" onsubmit="return confirm('Delete this category?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600 text-sm">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full bg-white rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                            No sub-categories yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @else
        <div class="text-center py-12 bg-white rounded-lg shadow">
            <i class="fas fa-tags text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500 text-lg mb-2">No categories yet</p>
            <p class="text-gray-400 text-sm mb-4">Create your first product category</p>
            <button 
                onclick="openCreateModal()"
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
            >
                <i class="fas fa-plus mr-2"></i>Add Category
            </button>
        </div>
    @endif

</div>

<!-- Create Modal -->
<div id="categoryCreateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold">Add Category</h4>
            <button onclick="closeCreateModal()" class="text-gray-600">&times;</button>
        </div>
        <form id="categoryCreateForm">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Name *</label>
                <input type="text" name="name" class="w-full px-4 py-2 border rounded" required />
                <p id="categoryCreateError" class="text-red-500 text-xs mt-1 hidden"></p>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Parent Category (optional)</label>
                <select name="parent_id" class="w-full px-4 py-2 border rounded">
                    <option value="">None (Main Category)</option>
                    @foreach(($categories ?? collect())->whereNull('parent_id')->sortBy('name') as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Select a parent to create a sub-category.</p>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea name="description" class="w-full px-4 py-2 border rounded"></textarea>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-gray-200 rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal(){
    document.getElementById('categoryCreateModal').classList.remove('hidden');
    document.getElementById('categoryCreateModal').classList.add('flex');
}
function closeCreateModal(){
    document.getElementById('categoryCreateModal').classList.add('hidden');
    document.getElementById('categoryCreateModal').classList.remove('flex');
}

document.getElementById('categoryCreateForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try{
        const res = await fetch("{{ route('categories.store') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: data
        });
        const json = await res.json();
        if(json.success){
            // reload to show new category
            location.reload();
        } else {
            document.getElementById('categoryCreateError').textContent = json.message || 'Failed to create';
            document.getElementById('categoryCreateError').classList.remove('hidden');
        }
    } catch(err){
        document.getElementById('categoryCreateError').textContent = 'Server error';
        document.getElementById('categoryCreateError').classList.remove('hidden');
    }
});

const categorySearchInput = document.getElementById('categorySearchInput');
const categoryCards = document.querySelectorAll('[data-category-card]');
categorySearchInput?.addEventListener('input', function () {
    const term = this.value.trim().toLowerCase();
    categoryCards.forEach(card => {
        const text = card.dataset.searchText || '';
        card.style.display = text.includes(term) ? '' : 'none';
    });
});
</script>
@endsection

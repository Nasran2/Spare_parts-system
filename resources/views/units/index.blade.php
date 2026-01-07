@extends('layouts.app')

@section('title', 'Units')
@section('page-title', 'Units')

@section('content')
<div class="space-y-6">
    
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Unit Management</h3>
            <p class="text-sm text-gray-600">Manage your measurement units</p>
        </div>
        <button 
            onclick="openCreateModal()" 
            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg"
        >
            <i class="fas fa-plus mr-2"></i>Add New Unit
        </button>
    </div>

    <!-- Units Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @if(isset($units) && $units->count())
            @foreach($units as $unit)
                <div class="bg-white rounded-lg shadow p-4">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-800">{{ $unit->name }} <span class="text-sm text-gray-500">({{ $unit->short_name }})</span></h4>
                        @php($m = rtrim(rtrim(number_format((float)$unit->base_unit_multiplier, 3, '.', ''), '0'), '.'))
                        <p class="text-sm text-gray-500 mt-2">Base multiplier: {{ $m }}</p>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-gray-500">Created: {{ $unit->created_at->format('Y-m-d') }}</div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('units.edit', $unit->id) }}" class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">Edit</a>
                            <form action="{{ route('units.destroy', $unit->id) }}" method="POST" onsubmit="return confirm('Delete this unit?');">
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
                <i class="fas fa-balance-scale text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg mb-2">Units are configured</p>
                <p class="text-gray-400 text-sm mb-4">Default units have been created from the seeder</p>
                <button 
                    onclick="openCreateModal()"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                >
                    <i class="fas fa-plus mr-2"></i>Add More Units
                </button>
            </div>
        @endif
    </div>

</div>

<!-- Create Modal -->
<div id="unitCreateModal" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="absolute inset-0 bg-black opacity-50"></div>
    <div class="bg-white rounded-lg shadow-lg w-full max-w-lg relative z-10 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Add New Unit</h3>
            <button onclick="closeCreateModal()" class="text-gray-500 hover:text-gray-700">✕</button>
        </div>

        <form id="unitCreateForm">
            <div>
                <label class="block text-sm font-medium text-gray-700">Name *</label>
                <input type="text" name="name" class="w-full px-3 py-2 border rounded mt-1" required />
                <p class="mt-1 text-xs text-red-500 hidden" data-error-for="name"></p>
            </div>

            <div class="mt-3">
                <label class="block text-sm font-medium text-gray-700">Short Name *</label>
                <input type="text" name="short_name" class="w-full px-3 py-2 border rounded mt-1" required />
                <p class="mt-1 text-xs text-red-500 hidden" data-error-for="short_name"></p>
            </div>

            <div class="mt-3">
                <label class="block text-sm font-medium text-gray-700">Base Multiplier</label>
                <input type="number" step="0.01" name="base_unit_multiplier" class="w-full px-3 py-2 border rounded mt-1" />
                <p class="mt-1 text-xs text-red-500 hidden" data-error-for="base_unit_multiplier"></p>
            </div>

            <div class="mt-4 flex items-center gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Create</button>
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-gray-200 rounded">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('unitCreateModal').classList.remove('hidden');
    document.getElementById('unitCreateModal').classList.add('flex');
}
function closeCreateModal() {
    document.getElementById('unitCreateModal').classList.add('hidden');
    document.getElementById('unitCreateModal').classList.remove('flex');
}

document.getElementById('unitCreateForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);

    // clear previous errors
    form.querySelectorAll('[data-error-for]').forEach(el => { el.classList.add('hidden'); el.textContent = ''; });

    try {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch("{{ route('units.store') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: data
        });

        if (res.status === 422) {
            const json = await res.json();
            if (json.errors) {
                Object.keys(json.errors).forEach(key => {
                    const el = form.querySelector('[data-error-for="' + key + '"]');
                    if (el) {
                        el.textContent = json.errors[key].join(' ');
                        el.classList.remove('hidden');
                    }
                });
            }
            return;
        }

        const json = await res.json();
        if (json.success) {
            // reload to show newly created unit (simple and reliable)
            window.location.reload();
        } else {
            alert(json.message || 'Unable to create unit');
        }
    } catch (err) {
        console.error(err);
        alert('An error occurred while creating unit. Check console for details.');
    }
});
</script>
@endsection

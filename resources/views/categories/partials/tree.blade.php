@php
    $childrenByParentCollection = $childrenByParent instanceof \Illuminate\Support\Collection
        ? $childrenByParent
        : collect($childrenByParent);

    $children = ($childrenByParentCollection->get($parentId) ?? collect())->sortBy('name');
@endphp

@if($children->count())
    <ul class="mt-2 space-y-2">
        @foreach($children as $child)
            <li>
                <div style="margin-left: {{ $level * 12 }}px;">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-medium text-gray-800 truncate">{{ $child->name }}</span>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-full whitespace-nowrap">
                                        {{ (int) ($child->products_count ?? 0) }} Products
                                    </span>
                                    <span class="px-2 py-1 bg-gray-200 text-gray-700 text-xs font-semibold rounded-full whitespace-nowrap">Sub</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a href="{{ route('categories.edit', $child->id) }}" class="px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs">Edit</a>
                                <form action="{{ route('categories.destroy', $child->id) }}" method="POST" onsubmit="return confirm('Delete this category?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-xs">Delete</button>
                                </form>
                            </div>
                        </div>

                        <div class="mt-2 pl-3 border-l border-gray-200">
                            @include('categories.partials.tree', [
                                'parentId' => $child->id,
                                'childrenByParent' => $childrenByParent,
                                'level' => $level + 1,
                            ])
                        </div>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif

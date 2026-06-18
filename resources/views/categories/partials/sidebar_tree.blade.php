@php
    $childrenByParentCollection = $childrenByParent instanceof \Illuminate\Support\Collection
        ? $childrenByParent
        : collect($childrenByParent);

    $children = ($childrenByParentCollection->get($parentId) ?? collect())->sortBy('name');
@endphp

@if($children->count())
    <ul class="mt-1 space-y-1 ml-5 border-l border-gray-100 pl-2">
        @foreach($children as $child)
            @php
                $hasChildren = (($childrenByParentCollection->get($child->id) ?? collect())->count() > 0);
            @endphp
            <li>
                <div class="flex items-center">
                    @if($hasChildren)
                        <button
                            type="button"
                            class="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-gray-600"
                            data-toggle-children
                            data-toggle-id="{{ $child->id }}"
                            aria-label="Toggle"
                        >
                            <span data-caret="{{ $child->id }}">▸</span>
                        </button>
                    @else
                        <span class="inline-block w-6 h-6"></span>
                    @endif

                    <button
                        type="button"
                        class="flex-1 flex items-center justify-between gap-2 px-2 py-1 rounded hover:bg-gray-50"
                        data-category-node
                        data-category-id="{{ $child->id }}"
                        data-category-name="{{ $child->name }}"
                    >
                        <span class="text-sm text-gray-700 truncate">{{ $child->name }}</span>
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-bold rounded-full whitespace-nowrap">
                            {{ (int) ($child->products_count ?? 0) }}
                        </span>
                    </button>
                </div>

                <div class="hidden" data-children-container="{{ $child->id }}">
                    @include('categories.partials.sidebar_tree', [
                        'parentId' => $child->id,
                        'childrenByParent' => $childrenByParentCollection,
                    ])
                </div>
            </li>
        @endforeach
    </ul>
@endif

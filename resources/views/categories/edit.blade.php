@extends('layouts.app')

@section('title', 'Edit Category')
@section('page-title', 'Edit Category')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-md p-6">
        <form action="{{ route('categories.update', $category->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Name *</label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" required class="w-full px-4 py-2 border rounded" />
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="mt-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Parent Category (optional)</label>
                <select name="parent_id" class="w-full px-4 py-2 border rounded">
                    <option value="">None (Main Category)</option>
                    @foreach(($parents ?? collect()) as $p)
                        <option value="{{ $p->id }}" @selected((int) old('parent_id', $category->parent_id) === (int) $p->id)>
                            {{ $p->name }}
                        </option>
                    @endforeach
                </select>
                @error('parent_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="mt-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea name="description" class="w-full px-4 py-2 border rounded">{{ old('description', $category->description) }}</textarea>
            </div>

            <div class="mt-4">
                <button class="px-4 py-2 bg-blue-600 text-white rounded">Update</button>
                <a href="{{ route('categories.index') }}" class="ml-2 text-sm text-gray-600">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

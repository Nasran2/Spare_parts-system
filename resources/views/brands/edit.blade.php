@extends('layouts.app')

@section('title', 'Edit Brand')
@section('page-title', 'Edit Brand')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-md p-6">
        <form action="{{ route('brands.update', $brand->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Name *</label>
                <input type="text" name="name" value="{{ old('name', $brand->name) }}" required class="w-full px-4 py-2 border rounded" />
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="mt-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea name="description" class="w-full px-4 py-2 border rounded">{{ old('description', $brand->description) }}</textarea>
            </div>

            <div class="mt-4">
                <button class="px-4 py-2 bg-blue-600 text-white rounded">Update</button>
                <a href="{{ route('brands.index') }}" class="ml-2 text-sm text-gray-600">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

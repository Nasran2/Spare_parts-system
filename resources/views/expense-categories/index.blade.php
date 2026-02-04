@extends('layouts.app')
@section('title','Expense Categories')
@section('page-title','Expense Categories')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Expense Categories</h2>
            <p class="text-sm text-gray-600">Manage categories used to classify expenses.</p>
        </div>
        <a href="{{ route('expense-categories.create') }}" class="inline-flex items-center px-5 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800">
            <i class="fas fa-plus mr-2"></i>New Category
        </a>
    </div>

    @if(session('success'))
        <div class="px-4 py-3 bg-green-100 text-green-700 rounded-lg">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="px-4 py-3 bg-red-100 text-red-700 rounded-lg">
            <ul class="text-sm list-disc list-inside">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Name</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Description</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Active</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($categories as $cat)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm font-medium text-gray-800">{{ $cat->name }}</td>
                    <td class="px-4 py-2 text-sm text-gray-600">{{ Str::limit($cat->description,60) }}</td>
                    <td class="px-4 py-2 text-sm">
                        @if($cat->is_active)
                            <span class="inline-flex items-center px-2 py-1 text-xs bg-green-100 text-green-700 rounded">Yes</span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 text-xs bg-red-100 text-red-600 rounded">No</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-sm text-right space-x-1">
                        <a href="{{ route('expense-categories.edit',$cat) }}" class="inline-block p-2 text-blue-600 hover:bg-blue-50 rounded" title="Edit"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('expense-categories.destroy',$cat) }}" method="POST" class="inline" onsubmit="return confirm('Delete this category?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No categories found. <a href="{{ route('expense-categories.create') }}" class="text-blue-600 hover:underline">Create one</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
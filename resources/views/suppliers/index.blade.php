@extends('layouts.app')

@section('title', 'Suppliers')
@section('page-title', 'Supplier Management')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
        </div>
    @endif
    
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Supplier Management</h3>
            <p class="text-sm text-gray-600">Manage your vehicle parts suppliers</p>
        </div>
        <a 
            href="{{ route('suppliers.create') }}" 
            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg"
        >
            <i class="fas fa-plus mr-2"></i>Add New Supplier
        </a>
    </div>

    <!-- Suppliers Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($suppliers as $supplier)
        <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-truck text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800">{{ $supplier->name }}</h3>
                        <p class="text-xs text-gray-500">{{ $supplier->company_name ?? 'Individual' }}</p>
                    </div>
                </div>
                @if($supplier->is_active)
                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Active</span>
                @else
                    <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">Inactive</span>
                @endif
            </div>

            <div class="space-y-2 text-sm">
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-phone w-5 text-blue-600"></i>
                    <span>{{ $supplier->phone ?? 'N/A' }}</span>
                </div>
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-envelope w-5 text-blue-600"></i>
                    <span class="truncate">{{ $supplier->email ?? 'N/A' }}</span>
                </div>
                <div class="flex items-start text-gray-600">
                    <i class="fas fa-map-marker-alt w-5 text-blue-600 mt-1"></i>
                    <span class="flex-1">{{ $supplier->address ?? 'No address' }}</span>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-between items-center">
                <span class="text-xs text-gray-500">
                    <i class="fas fa-shopping-cart mr-1"></i>0 Purchases
                </span>
                <div class="flex space-x-2">
                    <a href="{{ route('suppliers.show', $supplier->id) }}" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="{{ route('suppliers.edit', $supplier->id) }}" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST" onsubmit="return confirm('Delete this supplier?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full">
            <div class="bg-white rounded-xl shadow-md p-12 text-center">
                <i class="fas fa-truck text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg mb-2">No suppliers found</p>
                <p class="text-gray-400 text-sm mb-4">Add your first supplier to start purchasing</p>
                <a href="{{ route('suppliers.create') }}" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-plus mr-2"></i>Add Supplier
                </a>
            </div>
        </div>
        @endforelse
    </div>

</div>
@endsection

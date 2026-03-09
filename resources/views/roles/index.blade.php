@extends('layouts.app')

@section('title', 'Roles')
@section('page-title', 'Role Management')

@section('content')
<div class="space-y-6">
    
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Role Management</h3>
            <p class="text-sm text-gray-600">Manage user roles and permissions</p>
        </div>
        @if(auth()->user()?->hasPermission('roles.create'))
            <a 
                href="{{ route('roles.create') }}" 
                class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg"
            >
                <i class="fas fa-plus mr-2"></i>Add New Role
            </a>
        @endif
    </div>

    <!-- Roles Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($roles as $role)
        <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition">
            <!-- Role Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 text-white">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-xl font-bold mb-1">{{ $role->name }}</h3>
                        <p class="text-blue-100 text-sm">{{ $role->description }}</p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-shield text-2xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center space-x-4 text-sm">
                    <span class="flex items-center">
                        <i class="fas fa-users mr-2"></i>{{ $role->users_count }} Users
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-key mr-2"></i>{{ count($role->permissions ?? []) }} Permissions
                    </span>
                </div>
            </div>

            <!-- Permissions Preview -->
            <div class="p-6">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">
                    <i class="fas fa-lock text-blue-600 mr-2"></i>Permissions
                </h4>
                <div class="space-y-2 max-h-48 overflow-y-auto">
                    @if(!empty($role->permissions))
                        @foreach(array_slice($role->permissions, 0, 8) as $permission)
                        <div class="flex items-center text-sm">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span class="text-gray-600">{{ str_replace(['.', '-'], [' ', ' '], ucfirst($permission)) }}</span>
                        </div>
                        @endforeach
                        @if(count($role->permissions) > 8)
                        <p class="text-xs text-gray-500 italic mt-2">
                            + {{ count($role->permissions) - 8 }} more permissions
                        </p>
                        @endif
                    @else
                        <p class="text-gray-400 text-sm">No permissions assigned</p>
                    @endif
                </div>
            </div>

            <!-- Status & Actions -->
            <div class="px-6 pb-6">
                <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                    <div>
                        @if($role->is_active)
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                <i class="fas fa-check-circle mr-1"></i>Active
                            </span>
                        @else
                            <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">
                                <i class="fas fa-ban mr-1"></i>Inactive
                            </span>
                        @endif
                    </div>
                    <div class="flex space-x-2">
                        <a 
                            href="{{ route('roles.show', $role->id) }}"
                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                            title="View Details"
                        >
                            <i class="fas fa-eye"></i>
                        </a>
                        @if(auth()->user()?->hasPermission('roles.edit'))
                            <a 
                                href="{{ route('roles.edit', $role->id) }}"
                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                title="Edit"
                            >
                                <i class="fas fa-edit"></i>
                            </a>
                        @endif
                        @if(auth()->user()?->hasPermission('roles.delete') && $role->users_count === 0)
                            <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this role?')">
                                @csrf
                                @method('DELETE')
                                <button 
                                    type="submit"
                                    class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                                    title="Delete"
                                >
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full">
            <div class="bg-white rounded-xl shadow-md p-12 text-center">
                <i class="fas fa-user-shield text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg mb-2">No roles found</p>
                <p class="text-gray-400 text-sm mb-4">Create your first user role</p>
                @if(auth()->user()?->hasPermission('roles.create'))
                    <a 
                        href="{{ route('roles.create') }}"
                        class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        <i class="fas fa-plus mr-2"></i>Add Role
                    </a>
                @endif
            </div>
        </div>
        @endforelse
    </div>

    <!-- Role Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Roles</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $roles->count() }}</h3>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-shield text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Active Roles</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $roles->where('is_active', true)->count() }}</h3>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Users</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $roles->sum('users_count') }}</h3>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Avg Permissions</p>
                    <h3 class="text-2xl font-bold text-gray-800">
                        {{ $roles->count() > 0 ? round($roles->sum(function($r) { return count($r->permissions ?? []); }) / $roles->count()) : 0 }}
                    </h3>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-key text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

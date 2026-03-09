@extends('layouts.app')

@section('title', 'Role Details')
@section('page-title', 'Role Details')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="{{ route('roles.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-800 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Roles
        </a>
    </div>

    <!-- Role Details Card -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <!-- Card Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold flex items-center">
                        <i class="fas fa-shield-alt mr-3"></i>{{ $role->name }}
                    </h2>
                    <p class="text-blue-100 mt-1">{{ $role->description }}</p>
                </div>
                <div class="flex gap-2">
                    @if($role->is_active)
                        <span class="px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-semibold">
                            <i class="fas fa-check-circle mr-1"></i>Active
                        </span>
                    @else
                        <span class="px-4 py-2 bg-gray-500 text-white rounded-lg text-sm font-semibold">
                            <i class="fas fa-ban mr-1"></i>Inactive
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Card Body -->
        <div class="p-6 md:p-8 space-y-8">
            <!-- Role Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Assigned Users</p>
                            <h3 class="text-2xl font-bold text-gray-800">{{ $role->users_count }}</h3>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Permissions</p>
                            <h3 class="text-2xl font-bold text-gray-800">{{ count($role->permissions ?? []) }}</h3>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-key text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Status</p>
                            <h3 class="text-lg font-bold text-gray-800">
                                {{ $role->is_active ? 'Active' : 'Inactive' }}
                            </h3>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-{{ $role->is_active ? 'check-circle' : 'ban' }} text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permissions List -->
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-key text-blue-600 mr-2"></i>Permissions ({{ count($role->permissions ?? []) }})
                </h3>

                @if(!empty($role->permissions))
                    @php
                        $groupedPermissions = collect($role->permissions)->groupBy(function($permission) {
                            return ucfirst(explode('.', $permission)[0] ?? 'Other');
                        });
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($groupedPermissions as $module => $permissions)
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-folder text-blue-600 mr-2"></i>{{ $module }}
                            </h4>
                            <div class="space-y-2">
                                @foreach($permissions as $permission)
                                <div class="flex items-center text-sm bg-white p-2 rounded">
                                    <i class="fas fa-check text-green-500 mr-2"></i>
                                    <span class="text-gray-700">
                                        {{ ucfirst(str_replace(['.', '-'], [' ', ' '], explode('.', $permission)[1] ?? $permission)) }}
                                    </span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="bg-gray-50 rounded-lg p-8 text-center">
                        <i class="fas fa-exclamation-triangle text-4xl text-gray-400 mb-3"></i>
                        <p class="text-gray-500">No permissions assigned to this role</p>
                    </div>
                @endif
            </div>

            <!-- Assigned Users -->
            @if($role->users_count > 0)
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                    <i class="fas fa-users text-blue-600 mr-2"></i>Assigned Users ({{ $role->users_count }})
                </h3>

                <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 mr-3 mt-1"></i>
                        <div>
                            <p class="text-sm text-blue-800 font-medium mb-1">User Assignment</p>
                            <p class="text-sm text-blue-700">
                                This role is currently assigned to {{ $role->users_count }} user(s). 
                                @if(auth()->user()?->hasPermission('users.view'))
                                    <a href="{{ route('users.index') }}?role={{ $role->id }}" class="underline hover:text-blue-900">
                                        View users →
                                    </a>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Action Buttons -->
            <div class="flex flex-col md:flex-row gap-3 pt-6 border-t">
                @if(auth()->user()?->hasPermission('roles.edit'))
                    <a
                        href="{{ route('roles.edit', $role->id) }}"
                        class="flex-1 md:flex-none px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg font-semibold text-center"
                    >
                        <i class="fas fa-edit mr-2"></i>Edit Role
                    </a>
                @endif
                
                @if(auth()->user()?->hasPermission('roles.delete') && $role->users_count === 0)
                    <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="flex-1 md:flex-none" onsubmit="return confirm('Are you sure you want to delete this role? This action cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="w-full px-8 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-semibold"
                        >
                            <i class="fas fa-trash mr-2"></i>Delete Role
                        </button>
                    </form>
                @endif

                <a
                    href="{{ route('roles.index') }}"
                    class="flex-1 md:flex-none px-8 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition text-center font-semibold"
                >
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Edit User')
@section('page-title', 'Edit User')

@section('content')
<div class="space-y-6">
    
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Edit User</h3>
            <p class="text-sm text-gray-600">Update user information</p>
        </div>
        <a 
            href="{{ route('users.index') }}" 
            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition"
        >
            <i class="fas fa-arrow-left mr-2"></i>Back to Users
        </a>
    </div>

    <!-- Form -->
    <form action="{{ route('users.update', $user) }}" method="POST" class="bg-white rounded-xl shadow-md p-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Full Name -->
            <div class="md:col-span-2">
                <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-user text-blue-600 mr-2"></i>Full Name *
                </label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    value="{{ old('name', $user->name) }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-500 @enderror" 
                    required
                >
                @error('name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Username -->
            <div>
                <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-id-badge text-blue-600 mr-2"></i>Username *
                </label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="{{ old('username', $user->username) }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('username') border-red-500 @enderror" 
                    required
                >
                @error('username')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-envelope text-blue-600 mr-2"></i>Email Address *
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="{{ old('email', $user->email) }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('email') border-red-500 @enderror" 
                    required
                >
                @error('email')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Phone -->
            <div>
                <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-phone text-blue-600 mr-2"></i>Phone Number
                </label>
                <input 
                    type="tel" 
                    id="phone" 
                    name="phone" 
                    value="{{ old('phone', $user->phone) }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('phone') border-red-500 @enderror"
                >
                @error('phone')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-lock text-blue-600 mr-2"></i>New Password
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('password') border-red-500 @enderror"
                    placeholder="Leave blank to keep current password"
                >
                @error('password')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password</p>
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-lock text-blue-600 mr-2"></i>Confirm New Password
                </label>
                <input 
                    type="password" 
                    id="password_confirmation" 
                    name="password_confirmation" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Confirm new password"
                >
            </div>

            <!-- Role -->
            <div class="md:col-span-2">
                <label for="role_id" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-user-shield text-blue-600 mr-2"></i>User Role *
                </label>
                <select 
                    id="role_id" 
                    name="role_id" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('role_id') border-red-500 @enderror" 
                    required
                >
                    <option value="">Select a role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                            {{ $role->name }} - {{ $role->description }}
                        </option>
                    @endforeach
                </select>
                @error('role_id')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Stores -->
            <div class="md:col-span-2 hidden" id="store-selection-container">
                <label for="stores" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-store text-blue-600 mr-2"></i>Assigned Stores
                </label>
                <select 
                    id="stores" 
                    name="stores[]" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('stores') border-red-500 @enderror" 
                    multiple
                >
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ in_array($store->id, old('stores', $userStores ?? [])) ? 'selected' : '' }}>
                            {{ $store->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Hold Ctrl (Windows) or Command (Mac) to select multiple stores.</p>
                @error('stores')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            <div class="md:col-span-2 hidden" id="all-stores-notice">
                <div class="bg-blue-50 text-blue-700 p-4 rounded-lg flex items-center">
                    <i class="fas fa-info-circle mr-3 text-xl"></i>
                    <div>
                        <p class="font-semibold text-sm">All Stores Access</p>
                        <p class="text-xs mt-1">This role automatically has access to all stores in the system.</p>
                    </div>
                </div>
            </div>

            <!-- Is Active -->
            <div class="md:col-span-2">
                <label class="flex items-center space-x-3">
                    <input 
                        type="checkbox" 
                        name="is_active" 
                        value="1"
                        {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                        class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-2 focus:ring-blue-500"
                    >
                    <span class="text-sm font-semibold text-gray-700">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>Active User
                    </span>
                </label>
                <p class="text-xs text-gray-500 ml-8 mt-1">Inactive users cannot log in to the system</p>
            </div>

        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center justify-end space-x-4 mt-6 pt-6 border-t border-gray-200">
            <a 
                href="{{ route('users.index') }}" 
                class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition"
            >
                Cancel
            </a>
            <button 
                type="submit" 
                class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg"
            >
                <i class="fas fa-save mr-2"></i>Update User
            </button>
        </div>
    </form>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('role_id');
        const storeContainer = document.getElementById('store-selection-container');
        const allStoresNotice = document.getElementById('all-stores-notice');
        
        function updateStoreVisibility() {
            const selectedOption = roleSelect.options[roleSelect.selectedIndex];
            if (!selectedOption || !selectedOption.value) {
                storeContainer.classList.add('hidden');
                allStoresNotice.classList.add('hidden');
                return;
            }
            
            const roleName = selectedOption.text.toLowerCase();
            if (roleName.includes('admin')) {
                storeContainer.classList.add('hidden');
                allStoresNotice.classList.remove('hidden');
            } else {
                storeContainer.classList.remove('hidden');
                allStoresNotice.classList.add('hidden');
            }
        }
        
        roleSelect.addEventListener('change', updateStoreVisibility);
        updateStoreVisibility();
    });
</script>
@endsection

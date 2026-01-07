@extends('layouts.app')

@section('title', 'Business Information')
@section('page-title', 'Business Information')

@section('content')
<div class="space-y-6">
    
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Settings Navigation -->
        @include('settings.partials.sidebar')

        <!-- Settings Content -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-building text-blue-600 mr-2"></i>Business Information
                </h3>

                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.save') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-image mr-1"></i>Business Logo
                            </label>
                            <input 
                                type="file" 
                                name="shop_logo"
                                accept="image/*"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('shop_logo') border-red-500 @enderror"
                            >
                            @error('shop_logo')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @if(!empty($settings['shop_logo']))
                                <div class="mt-3 p-3 bg-gray-50 rounded-lg inline-block">
                                    <img src="{{ asset('storage/'.$settings['shop_logo']) }}" alt="Logo" class="h-20 object-contain">
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-store mr-1"></i>Business Name *
                            </label>
                            <input 
                                type="text" 
                                name="shop_name"
                                value="{{ old('shop_name', $settings['shop_name']) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('shop_name') border-red-500 @enderror"
                                required
                            >
                            @error('shop_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-quote-left mr-1"></i>Tagline / Small Heading
                            </label>
                            <input 
                                type="text" 
                                name="shop_tagline"
                                value="{{ old('shop_tagline', $settings['shop_tagline']) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('shop_tagline') border-red-500 @enderror"
                                placeholder="e.g. Auto Parts System"
                            >
                            @error('shop_tagline')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-1"></i>Business Email
                            </label>
                            <input 
                                type="email" 
                                name="shop_email"
                                value="{{ old('shop_email', $settings['shop_email']) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('shop_email') border-red-500 @enderror"
                            >
                            @error('shop_email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-phone mr-1"></i>Business Phone
                            </label>
                            <input 
                                type="tel" 
                                name="shop_phone"
                                value="{{ old('shop_phone', $settings['shop_phone']) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('shop_phone') border-red-500 @enderror"
                            >
                            @error('shop_phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-1"></i>Business Address
                            </label>
                            <textarea 
                                rows="3"
                                name="shop_address"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('shop_address') border-red-500 @enderror"
                            >{{ old('shop_address', $settings['shop_address']) }}</textarea>
                            @error('shop_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="pt-4 border-t border-gray-200 mt-6">
                            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition shadow-lg">
                                <i class="fas fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>

</div>
@endsection

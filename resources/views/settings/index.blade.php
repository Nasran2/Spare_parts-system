@extends('layouts.app')

@section('title', 'Settings')
@section('page-title', 'System Settings')

@section('content')
<div class="space-y-6">
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Settings Navigation -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-md p-4">
                <h3 class="font-bold text-gray-800 mb-4">Settings Menu</h3>
                <nav class="space-y-1">
                    <a href="#business" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-blue-50 text-blue-600 font-semibold">
                        <i class="fas fa-building"></i>
                        <span>Business Info</span>
                    </a>
                    <a href="#general" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-700">
                        <i class="fas fa-cog"></i>
                        <span>General Settings</span>
                    </a>
                    <a href="#invoice" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-700">
                        <i class="fas fa-file-invoice"></i>
                        <span>Invoice Settings</span>
                    </a>
                    <a href="#quotation" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-700">
                        <i class="fas fa-file-contract"></i>
                        <span>Quotation Settings</span>
                    </a>
                    <a href="#notifications" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-700">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6" id="business">
                    <i class="fas fa-building text-blue-600 mr-2"></i>Business Information
                </h3>

                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        {{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.save') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Business Logo</label>
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
                                <div class="mt-2">
                                    @php($logoPath = $settings['shop_logo'])
                                    @php($logoUrl = is_file(public_path($logoPath)) ? asset($logoPath) : asset('storage/'.$logoPath))
                                    <img src="{{ $logoUrl }}" alt="Logo" class="h-16 object-contain">
                                </div>
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Business Name *</label>
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
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Business Email</label>
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
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Business Phone</label>
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
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Business Address</label>
                            <textarea 
                                rows="3"
                                name="shop_address"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('shop_address') border-red-500 @enderror"
                            >{{ old('shop_address', $settings['shop_address']) }}</textarea>
                            @error('shop_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <hr class="my-6">

                        <h3 class="text-lg font-bold text-gray-800 mb-4" id="quotation">
                            <i class="fas fa-file-contract text-amber-600 mr-2"></i>Quotation Settings
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Validity (days)</label>
                                <input 
                                    type="number"
                                    name="quotation_valid_days"
                                    min="1" max="3650"
                                    value="{{ old('quotation_valid_days', $settings['quotation_valid_days']) }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('quotation_valid_days') border-red-500 @enderror"
                                >
                                @error('quotation_valid_days')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Terms & Conditions</label>
                                <textarea 
                                    rows="4"
                                    name="quotation_terms"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('quotation_terms') border-red-500 @enderror"
                                >{{ old('quotation_terms', $settings['quotation_terms']) }}</textarea>
                                @error('quotation_terms')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <hr class="my-6">

                        <h3 class="text-lg font-bold text-gray-800 mb-4" id="notifications">
                            <i class="fas fa-bell text-purple-600 mr-2"></i>Notification Settings
                        </h3>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <label class="font-semibold text-gray-700">Enable Stock Alerts</label>
                                    <p class="text-sm text-gray-500">Get notified when product stock is low</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="enable_stock_alerts" value="1" {{ old('enable_stock_alerts', $settings['enable_stock_alerts']) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Stock Alert Threshold</label>
                                <input 
                                    type="number"
                                    name="stock_alert_threshold"
                                    min="0" max="10000"
                                    value="{{ old('stock_alert_threshold', $settings['stock_alert_threshold']) }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('stock_alert_threshold') border-red-500 @enderror"
                                >
                                <p class="text-xs text-gray-500 mt-1">Alert when stock falls below this quantity</p>
                                @error('stock_alert_threshold')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <label class="font-semibold text-gray-700">Enable SMS Notifications</label>
                                    <p class="text-sm text-gray-500">Send SMS for important events</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="enable_sms_notifications" value="1" {{ old('enable_sms_notifications', $settings['enable_sms_notifications']) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                        </div>

                        <hr class="my-6">

                        <h3 class="text-lg font-bold text-gray-800 mb-4">
                            <i class="fas fa-sms text-green-600 mr-2"></i>SMS API Configuration
                        </h3>

                        <div class="space-y-4">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                                    <div>
                                        <p class="text-sm text-blue-800 font-semibold">SMS Provider Setup</p>
                                        <p class="text-xs text-blue-700 mt-1">Configure your SMS provider credentials below. Supported providers: Twilio, Nexmo, MSG91</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">SMS Provider</label>
                                <select 
                                    name="sms_provider"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('sms_provider') border-red-500 @enderror"
                                >
                                    <option value="twilio" {{ old('sms_provider', $settings['sms_provider']) == 'twilio' ? 'selected' : '' }}>Twilio</option>
                                    <option value="nexmo" {{ old('sms_provider', $settings['sms_provider']) == 'nexmo' ? 'selected' : '' }}>Nexmo (Vonage)</option>
                                    <option value="msg91" {{ old('sms_provider', $settings['sms_provider']) == 'msg91' ? 'selected' : '' }}>MSG91</option>
                                    <option value="custom" {{ old('sms_provider', $settings['sms_provider']) == 'custom' ? 'selected' : '' }}>Custom API</option>
                                </select>
                                @error('sms_provider')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        API Key / Auth Token
                                        <span class="text-xs text-gray-500 font-normal">(Required)</span>
                                    </label>
                                    <input 
                                        type="text"
                                        name="sms_api_key"
                                        value="{{ old('sms_api_key', $settings['sms_api_key']) }}"
                                        placeholder="Enter your API key"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('sms_api_key') border-red-500 @enderror"
                                    >
                                    @error('sms_api_key')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        API Secret / Auth Key
                                        <span class="text-xs text-gray-500 font-normal">(Optional)</span>
                                    </label>
                                    <input 
                                        type="password"
                                        name="sms_api_secret"
                                        value="{{ old('sms_api_secret', $settings['sms_api_secret']) }}"
                                        placeholder="Enter your API secret"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('sms_api_secret') border-red-500 @enderror"
                                    >
                                    @error('sms_api_secret')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        Account SID
                                        <span class="text-xs text-gray-500 font-normal">(For Twilio)</span>
                                    </label>
                                    <input 
                                        type="text"
                                        name="sms_account_sid"
                                        value="{{ old('sms_account_sid', $settings['sms_account_sid']) }}"
                                        placeholder="Twilio Account SID"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('sms_account_sid') border-red-500 @enderror"
                                    >
                                    @error('sms_account_sid')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        Sender ID / Phone Number
                                        <span class="text-xs text-gray-500 font-normal">(Required)</span>
                                    </label>
                                    <input 
                                        type="text"
                                        name="sms_sender_id"
                                        value="{{ old('sms_sender_id', $settings['sms_sender_id']) }}"
                                        placeholder="e.g., +1234567890 or BRANDNAME"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('sms_sender_id') border-red-500 @enderror"
                                    >
                                    @error('sms_sender_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                <div class="flex items-start">
                                    <i class="fas fa-exclamation-triangle text-amber-600 mt-1 mr-3"></i>
                                    <div>
                                        <p class="text-sm text-amber-800 font-semibold">Configuration Guide</p>
                                        <ul class="text-xs text-amber-700 mt-2 space-y-1 list-disc list-inside">
                                            <li><strong>Twilio:</strong> Get credentials from console.twilio.com</li>
                                            <li><strong>Nexmo:</strong> API Key and Secret from dashboard.nexmo.com</li>
                                            <li><strong>MSG91:</strong> Auth Key from control.msg91.com</li>
                                            <li>Keep your API credentials secure and never share them</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
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

@push('scripts')
<script>
    // Smooth scroll for settings navigation
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Update active state
                document.querySelectorAll('nav a').forEach(link => {
                    link.classList.remove('bg-blue-50', 'text-blue-600', 'font-semibold');
                    link.classList.add('hover:bg-gray-50', 'text-gray-700');
                });
                this.classList.add('bg-blue-50', 'text-blue-600', 'font-semibold');
                this.classList.remove('hover:bg-gray-50', 'text-gray-700');
            }
        });
    });

    // Toggle password visibility for API secret
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        const wrapper = input.parentElement;
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'absolute right-3 top-9 text-gray-500 hover:text-gray-700';
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
        
        wrapper.classList.add('relative');
        wrapper.appendChild(toggleBtn);
        
        toggleBtn.addEventListener('click', () => {
            if (input.type === 'password') {
                input.type = 'text';
                toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
</script>
@endpush

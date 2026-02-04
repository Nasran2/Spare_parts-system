@extends('layouts.app')

@section('title', 'Notification Settings')
@section('page-title', 'Notification Settings')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow">
        <div class="border-b p-6">
            <h3 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-cog text-blue-600 mr-3"></i>SMS & Notification Settings
            </h3>
            <p class="text-sm text-gray-600 mt-1">Configure your SMS provider and notification preferences</p>
        </div>

        @if(session('success'))
        <div class="mx-6 mt-6 bg-green-50 border border-green-300 rounded-lg p-4 flex items-start">
            <i class="fas fa-check-circle text-green-600 mr-3 mt-0.5"></i>
            <div>
                <p class="font-medium text-green-800">{{ session('success') }}</p>
            </div>
        </div>
        @endif

        <form method="POST" action="{{ route('notifications.settings.save') }}" class="p-6 space-y-6">
            @csrf

            <!-- SMS Provider Section -->
            <div class="border rounded-lg p-5 bg-gray-50">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-sms text-blue-600 mr-2"></i>SMS Provider Configuration
                </h4>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="flex items-center text-sm font-medium text-gray-700 mb-2">
                            Enable SMS Notifications
                            <input type="hidden" name="sms_enabled" value="0">
                            <input type="checkbox" name="sms_enabled" value="1" {{ $settings['sms_enabled'] ? 'checked' : '' }} class="ml-auto w-5 h-5 text-blue-600 rounded">
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center text-sm font-medium text-gray-700 mb-2">
                            Enable SMS in Notifications
                            <input type="hidden" name="enable_sms_notifications" value="0">
                            <input type="checkbox" name="enable_sms_notifications" value="1" {{ $settings['enable_sms_notifications'] ? 'checked' : '' }} class="ml-auto w-5 h-5 text-blue-600 rounded">
                        </label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">SMS Provider</label>
                    <select name="sms_provider" id="sms_provider" class="w-full border border-gray-300 rounded-lg p-2.5">
                        <option value="custom" {{ $settings['sms_provider'] == 'custom' ? 'selected' : '' }}>TextIt.biz (Custom API) - Recommended</option>
                        <option value="twilio" {{ $settings['sms_provider'] == 'twilio' ? 'selected' : '' }}>Twilio</option>
                        <option value="nexmo" {{ $settings['sms_provider'] == 'nexmo' ? 'selected' : '' }}>Nexmo/Vonage</option>
                        <option value="msg91" {{ $settings['sms_provider'] == 'msg91' ? 'selected' : '' }}>MSG91</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Most businesses use TextIt.biz for reliable SMS delivery</p>
                </div>

                <!-- TextIt.biz / Custom Provider Fields -->
                <div id="custom_fields" class="space-y-4 {{ $settings['sms_provider'] == 'custom' ? '' : 'hidden' }}">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h5 class="font-semibold text-blue-900 mb-2 flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>TextIt.biz HTTP API Setup
                        </h5>
                        <p class="text-sm text-blue-800 mb-3">Using BASIC HTTP API (Recommended for simplicity):</p>
                        <ol class="text-xs text-blue-700 space-y-2 ml-5 list-decimal">
                            <li>Go to <a href="https://textit.biz/MyAccount/Integration.php" target="_blank" class="underline font-medium">TextIt.biz Integration Page</a></li>
                            <li>Find <strong>BASIC HTTP API Credentials</strong> section</li>
                            <li>Copy your <strong>Username</strong> (e.g., 94758822269) and <strong>Password</strong> (e.g., 6886)</li>
                            <li>Paste them in the fields below</li>
                            <li>Keep the default URL: <code class="bg-white px-1 rounded">https://www.textit.biz/sendmsg/index.php</code></li>
                        </ol>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">API Endpoint URL</label>
                        <input type="url" name="sms_custom_url" value="{{ $settings['sms_custom_url'] }}" placeholder="https://www.textit.biz/sendmsg/index.php" class="w-full border border-gray-300 rounded-lg p-2.5">
                        <p class="text-xs text-gray-500 mt-1">Full API endpoint URL for sending SMS</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Username / API ID</label>
                            <input type="text" name="sms_custom_username" value="{{ $settings['sms_custom_username'] }}" placeholder="94758822269" class="w-full border border-gray-300 rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Password / API Key</label>
                            <input type="password" name="sms_custom_password" value="{{ $settings['sms_custom_password'] }}" placeholder="6886" class="w-full border border-gray-300 rounded-lg p-2.5">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sender ID (Optional)</label>
                        <input type="text" name="sms_sender_id" value="{{ $settings['sms_sender_id'] }}" placeholder="Your business name" maxlength="11" class="w-full border border-gray-300 rounded-lg p-2.5">
                        <p class="text-xs text-gray-500 mt-1">The name that appears as sender (max 11 characters)</p>
                    </div>
                </div>

                <!-- Twilio Fields -->
                <div id="twilio_fields" class="space-y-4 {{ $settings['sms_provider'] == 'twilio' ? '' : 'hidden' }}">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Account SID</label>
                            <input type="text" name="sms_account_sid" value="{{ $settings['sms_account_sid'] }}" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" class="w-full border border-gray-300 rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Auth Token</label>
                            <input type="password" name="sms_api_key" value="{{ $settings['sms_api_key'] }}" placeholder="Your auth token" class="w-full border border-gray-300 rounded-lg p-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Number</label>
                        <input type="text" name="sms_sender_id" value="{{ $settings['sms_sender_id'] }}" placeholder="+1234567890" class="w-full border border-gray-300 rounded-lg p-2.5">
                    </div>
                </div>

                <!-- Nexmo Fields -->
                <div id="nexmo_fields" class="space-y-4 {{ $settings['sms_provider'] == 'nexmo' ? '' : 'hidden' }}">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                            <input type="text" name="sms_api_key" value="{{ $settings['sms_api_key'] }}" placeholder="Your Nexmo API key" class="w-full border border-gray-300 rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">API Secret</label>
                            <input type="password" name="sms_api_secret" value="{{ $settings['sms_api_secret'] }}" placeholder="Your Nexmo API secret" class="w-full border border-gray-300 rounded-lg p-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Name</label>
                        <input type="text" name="sms_sender_id" value="{{ $settings['sms_sender_id'] }}" placeholder="Your business name" class="w-full border border-gray-300 rounded-lg p-2.5">
                    </div>
                </div>

                <!-- MSG91 Fields -->
                <div id="msg91_fields" class="space-y-4 {{ $settings['sms_provider'] == 'msg91' ? '' : 'hidden' }}">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Auth Key</label>
                        <input type="text" name="sms_api_key" value="{{ $settings['sms_api_key'] }}" placeholder="Your MSG91 auth key" class="w-full border border-gray-300 rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sender ID</label>
                        <input type="text" name="sms_sender_id" value="{{ $settings['sms_sender_id'] }}" placeholder="Your sender ID" class="w-full border border-gray-300 rounded-lg p-2.5">
                    </div>
                </div>
            </div>

            <!-- Stock Alert Settings -->
            <div class="border rounded-lg p-5 bg-gray-50">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-box text-orange-600 mr-2"></i>Stock Alert Settings
                </h4>

                <div class="mb-4">
                    <label class="flex items-center text-sm font-medium text-gray-700 mb-2">
                        Enable Low Stock Alerts
                        <input type="hidden" name="enable_stock_alerts" value="0">
                        <input type="checkbox" name="enable_stock_alerts" value="1" {{ $settings['enable_stock_alerts'] ? 'checked' : '' }} class="ml-auto w-5 h-5 text-blue-600 rounded">
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Low Stock Threshold</label>
                    <input type="number" name="stock_alert_threshold" value="{{ $settings['stock_alert_threshold'] }}" min="0" max="10000" class="w-full border border-gray-300 rounded-lg p-2.5">
                    <p class="text-xs text-gray-500 mt-1">Alert when product stock falls below this quantity</p>
                </div>
            </div>

            <!-- Monthly Due Reminder -->
            <div class="border rounded-lg p-5 bg-gray-50">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-calendar-check text-green-600 mr-2"></i>Monthly Due Reminder
                </h4>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="flex items-center text-sm font-medium text-gray-700 mb-2">
                            Enable Monthly Reminder
                            <input type="hidden" name="monthly_reminder_enabled" value="0">
                            <input type="checkbox" name="monthly_reminder_enabled" value="1" {{ $settings['monthly_reminder_enabled'] ? 'checked' : '' }} class="ml-auto w-5 h-5 text-green-600 rounded">
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Channel</label>
                        <select name="monthly_reminder_channel" class="w-full border border-gray-300 rounded-lg p-2.5">
                            <option value="sms" {{ $settings['monthly_reminder_channel'] == 'sms' ? 'selected' : '' }}>SMS</option>
                            @if(env('ENABLE_WHATSAPP', false))
                            <option value="whatsapp" {{ $settings['monthly_reminder_channel'] == 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                            <option value="both" {{ $settings['monthly_reminder_channel'] == 'both' ? 'selected' : '' }}>Both</option>
                            @endif
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Day of Month</label>
                        <input type="number" name="monthly_reminder_day" value="{{ $settings['monthly_reminder_day'] }}" min="1" max="31" class="w-full border border-gray-300 rounded-lg p-2.5">
                        <p class="text-xs text-gray-500 mt-1">Reminders run on this day each month</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Time (24h)</label>
                        <input type="text" name="monthly_reminder_time" value="{{ $settings['monthly_reminder_time'] }}" placeholder="09:00" class="w-full border border-gray-300 rounded-lg p-2.5">
                        <p class="text-xs text-gray-500 mt-1">HH:MM format, e.g., 09:00</p>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Message Template</label>
                    <textarea name="monthly_reminder_template" rows="3" class="w-full border border-gray-300 rounded-lg p-2.5">{{ $settings['monthly_reminder_template'] }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">Use placeholders: {name}, {due}, {date}</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Excluded Customers</label>
                    <div class="flex items-center justify-between mb-2">
                        <input type="text" id="excludeCustomerSearch" placeholder="Search customers..." class="text-xs border rounded px-2 py-1 w-64" onkeyup="filterExcludedCustomers()">
                        <span class="text-xs text-gray-500">Search by name or phone</span>
                    </div>
                    <div id="excludeCustomerList" class="border rounded p-3 bg-white max-h-52 overflow-y-auto space-y-2">
                        @foreach($customers as $c)
                            <label class="flex items-center text-sm excluded-customer-item" data-name="{{ strtolower($c->name) }}" data-phone="{{ $c->phone }}">
                                <input type="checkbox" name="monthly_reminder_excluded[]" value="{{ $c->id }}" class="mr-2" {{ in_array($c->id, $settings['monthly_reminder_excluded'] ?? []) ? 'checked' : '' }}>
                                <span class="{{ $c->is_active ? 'text-gray-800' : 'text-gray-400' }}">{{ $c->name }} @if($c->phone)<span class="text-xs text-gray-500">({{ $c->phone }})</span>@endif @if(!$c->is_active)<span class="text-xs text-red-500">[Inactive]</span>@endif</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Selected customers will not receive the monthly due reminder</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" formaction="{{ route('notifications.reminder.send_now') }}" formmethod="POST" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm" onclick="return confirm('Send reminders now?')">
                        <i class="fas fa-paper-plane mr-2"></i>Send Now
                    </button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end gap-3 pt-4 border-t">
                <a href="{{ route('notifications.index') }}" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                    <i class="fas fa-save mr-2"></i>Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const providerSelect = document.getElementById('sms_provider');
    const customFields = document.getElementById('custom_fields');
    const twilioFields = document.getElementById('twilio_fields');
    const nexmoFields = document.getElementById('nexmo_fields');
    const msg91Fields = document.getElementById('msg91_fields');

    providerSelect.addEventListener('change', function() {
        // Hide all provider fields
        customFields.classList.add('hidden');
        twilioFields.classList.add('hidden');
        nexmoFields.classList.add('hidden');
        msg91Fields.classList.add('hidden');

        // Show selected provider fields
        switch(this.value) {
            case 'custom':
                customFields.classList.remove('hidden');
                break;
            case 'twilio':
                twilioFields.classList.remove('hidden');
                break;
            case 'nexmo':
                nexmoFields.classList.remove('hidden');
                break;
            case 'msg91':
                msg91Fields.classList.remove('hidden');
                break;
        }
    });
});

function filterExcludedCustomers() {
    const searchTerm = document.getElementById('excludeCustomerSearch').value.toLowerCase();
    const items = document.querySelectorAll('.excluded-customer-item');
    items.forEach(item => {
        const name = item.getAttribute('data-name') || '';
        const phone = (item.getAttribute('data-phone') || '').toLowerCase();
        if (name.includes(searchTerm) || phone.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>
@endsection

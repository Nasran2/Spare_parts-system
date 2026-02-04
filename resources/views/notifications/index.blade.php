@extends('layouts.app')

@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
<div class="max-w-5xl mx-auto">
    <!-- SMS Settings Alert -->
    @php
        $smsEnabled = \App\Models\Setting::get('sms_enabled', false);
        $smsProvider = \App\Models\Setting::get('sms_provider', '');
        $smsApiKey = \App\Models\Setting::get('sms_api_key', '');
        $smsCustomUrl = \App\Models\Setting::get('sms_custom_url', '');
        $smsConfigured = $smsEnabled && $smsProvider && ($smsApiKey || $smsCustomUrl);
    @endphp
    
    @if(!$smsConfigured)
    <div class="bg-yellow-50 border border-yellow-300 rounded-xl shadow-sm p-5 mb-5">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mr-3 mt-1"></i>
            <div class="flex-1">
                <h4 class="font-bold text-yellow-800 mb-1">SMS Notifications Not Configured</h4>
                <p class="text-sm text-yellow-700 mb-3">Set up your SMS API to send notifications to customers. We recommend TextIt.biz for most businesses.</p>
                <a href="{{ route('notifications.settings') }}" class="inline-block bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium px-4 py-2 rounded transition">
                    <i class="fas fa-cog mr-1"></i> Configure SMS Settings
                </a>
            </div>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-bell text-blue-600 mr-2"></i>Notifications
                @if($unreadCount > 0)
                    <span class="ml-2 text-xs bg-red-100 text-red-600 px-2 py-1 rounded">{{ $unreadCount }} unread</span>
                @endif
            </h3>
            <a href="{{ route('notifications.settings') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                <i class="fas fa-cog mr-1"></i> Settings
            </a>
        </div>
        <div class="space-y-3 max-h-[600px] overflow-y-auto">
            @forelse($notifications as $n)
                <div class="p-4 rounded border flex justify-between items-start {{ $n->read_at ? 'bg-gray-50' : 'bg-yellow-50 border-yellow-300' }}">
                    <div>
                        <p class="font-semibold text-gray-800">{{ $n->title }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ $n->message }}</p>
                        <p class="text-xs text-gray-400 mt-2">{{ $n->created_at->diffForHumans() }} • {{ $n->type }}</p>
                    </div>
                    @if(!$n->read_at)
                    <form method="post" action="{{ route('notifications.read', $n->id) }}" class="ml-4">
                        @csrf
                        <button class="text-xs px-3 py-1 bg-blue-600 text-white rounded">Mark Read</button>
                    </form>
                    @endif
                </div>
            @empty
                <div class="text-center py-12 text-gray-400">
                    <i class="fas fa-bell-slash text-6xl mb-3"></i>
                    <p class="text-lg">No notifications yet</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

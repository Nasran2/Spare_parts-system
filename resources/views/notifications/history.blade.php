@extends('layouts.app')

@section('title', 'Promotion History')
@section('page-title', 'Promotion History')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-history text-purple-600 mr-2"></i>Promotion History
        </h3>
        <div class="space-y-3 max-h-[600px] overflow-y-auto">
            @forelse($promotions as $promo)
                <div class="p-4 rounded border bg-purple-50">
                    <div class="flex justify-between items-start mb-2">
                        <p class="font-semibold text-gray-800">{{ $promo->title }}</p>
                        <span class="text-xs text-gray-500">{{ $promo->created_at->format('M d, Y H:i') }}</span>
                    </div>
                    <p class="text-sm text-gray-700 bg-white p-2 rounded border mb-2">{{ $promo->message }}</p>
                    @if($promo->data)
                        <div class="text-xs text-gray-600 space-y-1">
                            <p><strong>Channel:</strong> {{ ucfirst($promo->data['channel'] ?? 'N/A') }}</p>
                            <p><strong>Recipients:</strong> {{ $promo->data['customer_count'] ?? 0 }}</p>
                            @if(!empty($promo->data['whatsapp_links']))
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-blue-600">View WhatsApp Links ({{ count($promo->data['whatsapp_links']) }})</summary>
                                    <div class="mt-2 space-y-1 pl-4">
                                        @foreach($promo->data['whatsapp_links'] as $idx => $link)
                                            <a href="{{ $link }}" target="_blank" class="text-blue-600 hover:underline block">
                                                <i class="fab fa-whatsapp mr-1"></i>Customer {{ $idx + 1 }}
                                            </a>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-center text-gray-400 py-12">No promotion history</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

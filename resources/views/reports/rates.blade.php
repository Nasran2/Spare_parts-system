@extends('layouts.app')

@section('title', 'Rate Conversion')
@section('page-title', 'Rate Conversion')

@section('content')
<div class="space-y-6">
    <form method="get" action="{{ route('reports.rates') }}" class="bg-white p-4 rounded shadow flex flex-wrap gap-4 items-end">
        <div>
            <label class="text-sm font-medium text-gray-600">Store</label>
            <select name="store_id" class="mt-1 border rounded px-3 py-2 text-sm w-48 bg-white">
                <option value="">All Stores</option>
                @if(isset($stores))
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                @endif
            </select>
        </div>

        <div>
            <label class="text-sm font-medium text-gray-600">Base Currency</label>
            <select name="base" class="mt-1 border rounded px-3 py-2 text-sm w-56">
                @foreach($currencies as $code => $label)
                    <option value="{{ $code }}" @selected($base === $code)>{{ $code }} — {{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[240px]">
            <label class="text-sm font-medium text-gray-600">Target Codes (comma separated)</label>
            <input type="text" name="targets" value="{{ implode(',', $symbols) }}" class="mt-1 border rounded px-3 py-2 text-sm w-full" placeholder="e.g. LKR,USD,EUR" />
        </div>
        <div class="flex items-center gap-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm" name="refresh" value="1">Refresh Rates</button>
            <a href="{{ route('reports.rates') }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
        </div>
    </form>

    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">Base</p>
            <p class="text-xl font-semibold">{{ $base }}</p>
            <p class="text-xs text-gray-500 mt-2">Last Updated</p>
            <p class="text-sm">
                @if($data['success'])
                    {{ $data['date'] ?? '—' }}
                @else
                    <span class="text-red-600 font-semibold">Offline</span> — {{ $data['error'] }}
                @endif
            </p>
            @if(!$data['success'])
                <p class="mt-2 text-red-600 text-sm">Rates are unavailable. Converter uses base=1.0.</p>
            @endif
            <div class="mt-4 border-t pt-3">
                <h5 class="font-semibold text-sm mb-2">Quick Pair Check</h5>
                <form method="get" action="{{ route('reports.rates') }}" class="flex flex-wrap items-end gap-2">
                    <input type="hidden" name="base" value="{{ $base }}" />
                    <input type="hidden" name="targets" value="{{ implode(',', $symbols) }}" />
                    <div>
                        <label class="text-xs text-gray-600">From</label>
                        <select name="pair_from" class="mt-1 border rounded px-2 py-1 text-xs w-40">
                            @foreach($currencies as $code => $label)
                                <option value="{{ $code }}" @selected($pairFrom === $code)>{{ $code }} — {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">To</label>
                        <select name="pair_to" class="mt-1 border rounded px-2 py-1 text-xs w-40">
                            @foreach($currencies as $code => $label)
                                <option value="{{ $code }}" @selected($pairTo === $code)>{{ $code }} — {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">Amount</label>
                        <input type="number" step="0.01" name="pair_amount" value="{{ $pairAmount }}" class="mt-1 border rounded px-2 py-1 text-xs w-24" />
                    </div>
                    <button class="px-3 py-1 bg-gray-100 rounded text-xs">Check</button>
                </form>
                <div class="mt-2 text-sm">
                    @if(!is_null($pairRate))
                        <div>1 {{ $pairFrom }} = <span class="font-semibold">{{ number_format($pairRate, 6) }}</span> {{ $pairTo }}</div>
                        <div class="text-xs text-gray-600">1 {{ $pairTo }} ≈ {{ $pairInverse ? number_format($pairInverse, 6) : '—' }} {{ $pairFrom }}</div>
                        <div class="text-xs mt-1">Result: {{ number_format($pairAmount, 2) }} {{ $pairFrom }} = {{ number_format($pairResult, 6) }} {{ $pairTo }}</div>
                    @else
                        <div class="text-gray-600">Pair rate unavailable (offline or missing pair in targets).</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="md:col-span-2 bg-white p-4 rounded shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left">
                        <th class="px-3 py-2">Currency</th>
                        <th class="px-3 py-2">Rate</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($symbols as $code)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ $code }}</td>
                        <td class="px-3 py-2">{{ number_format($data['rates'][$code] ?? 0, 6) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-3 py-6 text-center text-gray-500">No currencies selected.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white p-4 rounded shadow">
        <h4 class="font-semibold mb-2 text-sm">Quick Converter</h4>
        <form method="get" action="{{ route('reports.rates') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-sm font-medium text-gray-600">Base</label>
                <select name="base" class="mt-1 border rounded px-3 py-2 text-sm w-48">
                    @foreach($currencies as $code => $label)
                        <option value="{{ $code }}" @selected($base === $code)>{{ $code }} — {{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="targets" value="{{ implode(',', $symbols) }}" />
            <div>
                <label class="text-sm font-medium text-gray-600">Amount ({{ $base }})</label>
                <input type="number" name="amount" step="0.01" value="{{ $amount }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
            </div>
            <div>
                <label class="text-sm font-medium text-gray-600">Convert To</label>
                <select name="convert_to" class="mt-1 border rounded px-3 py-2 text-sm w-48">
                    @foreach($symbols as $code)
                        <option value="{{ $code }}" @selected($convertTo === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button class="bg-emerald-600 text-white px-4 py-2 rounded text-sm">Convert</button>
            </div>
        </form>
        <div class="mt-3 text-sm">
            @if(!is_null($converted))
                <span class="font-semibold">Result:</span> {{ number_format($amount, 2) }} {{ $base }} = {{ number_format($converted, 2) }} {{ $convertTo }}
            @else
                <span class="text-gray-600">Select a target currency to convert.</span>
            @endif
        </div>
    </div>
</div>
@endsection

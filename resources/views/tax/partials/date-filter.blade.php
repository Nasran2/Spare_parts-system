<form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    @if(isset($type))
        <input type="hidden" name="type" value="{{ $type }}">
    @endif
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Quick Range</label>
        <select name="range" class="rounded-lg border-gray-300 px-3 py-2">
            @foreach([
                'custom' => 'Custom Date',
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'this_week' => 'This Week',
                'last_week' => 'Last Week',
                'this_month' => 'This Month',
                'last_month' => 'Last Month',
                'this_quarter' => 'This Quarter',
                'last_quarter' => 'Last Quarter',
                'this_year' => 'This Year',
            ] as $value => $label)
                <option value="{{ $value }}" @selected(request('range', 'custom') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">From</label>
        <input type="date" name="from" value="{{ $from }}" class="rounded-lg border-gray-300 px-3 py-2">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">To</label>
        <input type="date" name="to" value="{{ $to }}" class="rounded-lg border-gray-300 px-3 py-2">
    </div>
    @if(request()->routeIs('tax.ledger'))
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Invoice Number</label>
            <input name="invoice" value="{{ request('invoice') }}" class="rounded-lg border-gray-300 px-3 py-2" placeholder="Search invoice">
        </div>
    @endif
    <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"><i class="fas fa-filter mr-1"></i>Apply</button>
    <a href="{{ url()->current() }}" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700">Reset</a>
</form>

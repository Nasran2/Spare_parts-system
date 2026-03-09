<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secret POS Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap');
        body { font-family: 'Roboto', sans-serif; }
        .brand-font { font-family: 'Rajdhani', sans-serif; }
        .glass-card {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-white p-6">
    <div class="relative z-10 max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 brand-font uppercase tracking-wide flex items-center">
                    <i class="fas fa-user-secret text-blue-600 mr-3"></i> Secret Configuration
                </h1>
                <p class="text-slate-600 text-sm mt-1 border-l-2 border-blue-500 pl-3">Control hidden sales, override stats, and error mode.</p>
            </div>
            <a href="{{ route('information.card') }}" class="group flex items-center text-slate-600 hover:text-slate-900 transition-colors">
                <div class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center mr-2 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    <i class="fas fa-arrow-left text-sm"></i>
                </div>
                <span class="text-sm font-medium uppercase tracking-wider">Back</span>
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl mb-6 flex items-center shadow-sm">
                <i class="fas fa-check-circle text-xl mr-3"></i>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
        @endif

        {{-- <!-- Date Filters for Actual Totals -->
        <form method="GET" action="{{ route('information.secret') }}" class="bg-white rounded-2xl p-6 shadow mb-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center text-slate-700">
                    <i class="fa-solid fa-filter mr-2 text-blue-600"></i>
                    <h3 class="text-lg font-bold brand-font uppercase tracking-wider text-slate-900">Date Filter</h3>
                </div>
                <div class="text-xs text-slate-500">Filters affect Actual totals and range counts</div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Date From</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl px-4 py-2.5 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Date To</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl px-4 py-2.5 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full md:w-auto bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl font-bold uppercase tracking-wider shadow">Apply</button>
                </div>
            </div>
        </form> --}}

        <form method="POST" action="{{ route('information.secret.save') }}" class="space-y-6" novalidate id="settingsForm">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Access Code -->
                <div class="bg-white rounded-2xl p-6 lg:col-span-1 shadow">
                    <div class="flex items-center mb-4 text-blue-600">
                        <i class="fas fa-key mr-2"></i>
                        <h3 class="text-lg font-bold brand-font uppercase tracking-wider text-slate-900">Access Security</h3>
                    </div>
                    
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">4-digit Pin Code</label>
                    <div class="relative">
                        <input type="text" name="code" value="{{ $config['code'] }}" maxlength="4" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{4}" required 
                               class="w-full bg-white border border-slate-300 text-slate-900 text-center text-2xl font-bold rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all font-mono tracking-[0.5em]" 
                               oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                        <i class="fas fa-lock absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    </div>
                </div>

                <!-- Emergency Mode -->
                <div class="bg-white rounded-2xl p-6 lg:col-span-2 shadow">
                    
                    <div class="flex items-center justify-between">
                        <div class="pr-8">
                            <div class="flex items-center mb-2 text-red-600">
                                <i class="fas fa-radiation-alt mr-2"></i>
                                <h3 class="text-lg font-bold brand-font uppercase tracking-wider text-slate-900">Critical Error Mode</h3>
                            </div>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                Instantly hides the entire application behind a realistic "Connection Error" screen. 
                                <span class="text-red-600">Only developer info will be visible.</span>
                            </p>
                        </div>

                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="force_error_mode" value="1" {{ $config['force_error_mode'] ? 'checked' : '' }} class="sr-only peer">
                            <div class="w-14 h-7 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Overrides -->
            {{-- <div class="bg-white rounded-2xl p-6 shadow">
                <div class="flex items-center mb-6 text-orange-600 border-b border-slate-100 pb-4">
                    <i class="fas fa-tachometer-alt mr-2"></i>
                    <h3 class="text-lg font-bold brand-font uppercase tracking-wider text-slate-900">Display Overrides</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Override Total Sales Amount</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-slate-500 font-bold">$</span>
                            </div>
                            <input type="number" step="0.01" name="override_total_sales_amount" value="{{ $config['override_total_sales_amount'] }}" 
                                   class="w-full pl-8 bg-white border border-slate-300 text-slate-900 rounded-xl px-4 py-3 focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all font-mono" placeholder="0.00">
                            <p class="mt-2 text-xs text-slate-500">Actual in range: <span class="font-semibold text-slate-800">{{ trim(config('app.currency','Rs ')) }} {{ number_format((float)($actual['amount'] ?? 0), 2) }}</span></p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Override Sales Count</label>
                        <div class="relative">
                             <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-hashtag text-slate-500 text-xs"></i>
                            </div>
                            <input type="number" name="override_sales_count" value="{{ $config['override_sales_count'] }}" 
                                   class="w-full pl-8 bg-white border border-slate-300 text-slate-900 rounded-xl px-4 py-3 focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition-all font-mono" placeholder="0">
                            <p class="mt-2 text-xs text-slate-500">Actual count in range: <span class="font-semibold text-slate-800">{{ $actual['count'] ?? 0 }}</span></p>
                        </div>
                    </div>
                </div>
            </div> --}}

            <!-- Hidden Ranges (Separate Sales / Purchases) -->
            <div class="bg-white rounded-2xl p-6 shadow">
                <div class="flex items-center justify-between mb-6 border-b border-slate-100 pb-4">
                    <div class="flex items-center text-blue-600">
                        <i class="fas fa-eye-slash mr-2"></i>
                        <h3 class="text-lg font-bold brand-font uppercase tracking-wider text-slate-900">Hidden Sales Ranges</h3>
                    </div>
                    <button type="button" onclick="addSalesRange()" class="text-xs bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded-lg font-bold uppercase tracking-wider transition-colors shadow">
                        <i class="fas fa-plus mr-1"></i> Add Sales Range
                    </button>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-xs text-blue-800 flex items-start">
                    <i class="fas fa-info-circle mt-0.5 mr-2 flex-shrink-0"></i>
                    <span>Sales with total amounts falling within these ranges will be completely hidden from the system and reports.</span>
                </div>

                <div id="sales-ranges" class="space-y-3">
                    @foreach($config['hidden_ranges_sales'] as $idx => $r)
                        <div class="range-row grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-slate-50 p-3 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors group">
                            <div class="md:col-span-4">
                                <label class="block text-[10px] text-slate-500 uppercase tracking-wider mb-1 px-1">Min Amount</label>
                                <input type="number" name="sales_ranges[{{ $idx }}][min]" value="{{ $r['min'] }}" class="w-full bg-white border border-slate-300 text-slate-900 text-sm rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none" placeholder="Min">
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-[10px] text-slate-500 uppercase tracking-wider mb-1 px-1">Max Amount</label>
                                <input type="number" name="sales_ranges[{{ $idx }}][max]" value="{{ $r['max'] }}" class="w-full bg-white border border-slate-300 text-slate-900 text-sm rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none" placeholder="Max">
                            </div>
                            <div class="md:col-span-4 flex items-end justify-between h-full pb-2">
                                <label class="flex items-center cursor-pointer select-none">
                                    <input type="hidden" name="sales_ranges[{{ $idx }}][hide]" value="0">
                                    <input type="checkbox" name="sales_ranges[{{ $idx }}][hide]" value="1" {{ $r['hide'] ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-blue-600 bg-white focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-slate-700 group-hover:text-slate-900 transition-colors">Active</span>
                                </label>
                                <button type="button" class="text-xs bg-white border border-slate-300 text-slate-700 px-2.5 py-1 rounded-md hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 transition" onclick="openRangeBills({{ $r['min'] }}, {{ $r['max'] }}, 'sale')" title="View sales in this range">
                                    <i class="fa-solid fa-receipt mr-1"></i>{{ $salesRangeCounts[$idx] ?? 0 }} bills
                                </button>
                                <button type="button" class="text-slate-500 hover:text-red-600 p-1.5 rounded-md hover:bg-red-50 transition-all" onclick="removeRange(this)" title="Remove Range">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(count($config['hidden_ranges_sales']) === 0)
                    <div id="empty-sales-ranges" class="text-center py-8 text-slate-500 text-sm">
                        No hidden sales ranges configured.
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-2xl p-6 shadow mt-6">
                <div class="flex items-center justify-between mb-6 border-b border-slate-100 pb-4">
                    <div class="flex items-center text-blue-600">
                        <i class="fas fa-eye-slash mr-2"></i>
                        <h3 class="text-lg font-bold brand-font uppercase tracking-wider text-slate-900">Hidden Purchase Ranges</h3>
                    </div>
                    <button type="button" onclick="addPurchaseRange()" class="text-xs bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded-lg font-bold uppercase tracking-wider transition-colors shadow">
                        <i class="fas fa-plus mr-1"></i> Add Purchase Range
                    </button>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-xs text-blue-800 flex items-start">
                    <i class="fas fa-info-circle mt-0.5 mr-2 flex-shrink-0"></i>
                    <span>Purchases with total amounts falling within these ranges will be completely hidden from the system and reports.</span>
                </div>

                <div id="purchase-ranges" class="space-y-3">
                    @foreach($config['hidden_ranges_purchases'] as $idx => $r)
                        <div class="range-row grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-slate-50 p-3 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors group">
                            <div class="md:col-span-4">
                                <label class="block text-[10px] text-slate-500 uppercase tracking-wider mb-1 px-1">Min Amount</label>
                                <input type="number" name="purchase_ranges[{{ $idx }}][min]" value="{{ $r['min'] }}" class="w-full bg-white border border-slate-300 text-slate-900 text-sm rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none" placeholder="Min">
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-[10px] text-slate-500 uppercase tracking-wider mb-1 px-1">Max Amount</label>
                                <input type="number" name="purchase_ranges[{{ $idx }}][max]" value="{{ $r['max'] }}" class="w-full bg-white border border-slate-300 text-slate-900 text-sm rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none" placeholder="Max">
                            </div>
                            <div class="md:col-span-4 flex items-end justify-between h-full pb-2">
                                <label class="flex items-center cursor-pointer select-none">
                                    <input type="hidden" name="purchase_ranges[{{ $idx }}][hide]" value="0">
                                    <input type="checkbox" name="purchase_ranges[{{ $idx }}][hide]" value="1" {{ $r['hide'] ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-blue-600 bg-white focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-slate-700 group-hover:text-slate-900 transition-colors">Active</span>
                                </label>
                                <button type="button" class="text-xs bg-white border border-slate-300 text-slate-700 px-2.5 py-1 rounded-md hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 transition" onclick="openRangeBills({{ $r['min'] }}, {{ $r['max'] }}, 'purchase')" title="View purchases in this range">
                                    <i class="fa-solid fa-receipt mr-1"></i>{{ $purchaseRangeCounts[$idx] ?? 0 }} bills
                                </button>
                                <button type="button" class="text-slate-500 hover:text-red-600 p-1.5 rounded-md hover:bg-red-50 transition-all" onclick="removeRange(this)" title="Remove Range">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(count($config['hidden_ranges_purchases']) === 0)
                    <div id="empty-purchase-ranges" class="text-center py-8 text-slate-500 text-sm">
                        No hidden purchase ranges configured.
                    </div>
                @endif
            </div>

            <div class="pt-4 pb-12">
                <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white uppercase tracking-widest font-bold rounded-xl py-4 shadow transition-all active:scale-[0.99] brand-font text-xl flex items-center justify-center">
                    <i class="fas fa-save mr-3"></i> Save Configuration
                </button>
            </div>
        </form>

        <div class="mt-8 border-t border-white/10 pt-6">
             @php($dev = config('services.developer'))
            <div class="bg-white rounded-xl p-5 inline-block min-w-[250px] shadow">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Developer Contact Info</p>
                <ul class="space-y-2 text-sm text-slate-700">
                    <li class="flex items-center"><i class="fas fa-globe w-5 text-slate-400"></i> {{ $dev['website'] ?? 'N/A' }}</li>
                    <li class="flex items-center"><i class="fas fa-phone w-5 text-slate-400"></i> {{ $dev['phone'] ?? 'N/A' }}</li>
                    <li class="flex items-center"><i class="fas fa-envelope w-5 text-slate-400"></i> {{ $dev['email'] ?? 'N/A' }}</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Modal for Range Bills -->
    <div id="rangeModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40" onclick="closeRangeModal()"></div>
        <div class="absolute inset-x-0 top-10 mx-auto max-w-3xl bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 bg-slate-50">
                <h4 class="text-slate-800 font-semibold"><i class="fa-solid fa-list mr-2 text-blue-600"></i><span id="rangeModalTitle">Bills in Range</span></h4>
                <button class="text-slate-500 hover:text-slate-800" onclick="closeRangeModal()"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div id="rangeModalBody" class="p-5">
                <div class="text-center text-slate-500">Loading…</div>
            </div>
        </div>
    </div>

    <script>
        function createEmptyMessage(containerId){
            const msg = document.createElement('div');
            msg.className = 'text-center py-8 text-slate-500 text-sm';
            if(containerId === 'sales-ranges'){
                msg.id = 'empty-sales-ranges';
                msg.textContent = 'No hidden sales ranges configured.';
            } else {
                msg.id = 'empty-purchase-ranges';
                msg.textContent = 'No hidden purchase ranges configured.';
            }
            return msg;
        }

        function addRange(containerId, inputPrefix, emptyId){
            const container = document.getElementById(containerId);
            const idx = container.querySelectorAll('.range-row').length;

            const emptyMsg = document.getElementById(emptyId);
            if(emptyMsg) emptyMsg.remove();

            const wrapper = document.createElement('div');
            wrapper.className = 'range-row grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-slate-50 p-3 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors group';
            wrapper.innerHTML = `
                <div class="md:col-span-4">
                    <label class="block text-[10px] text-slate-500 uppercase tracking-wider mb-1 px-1">Min Amount</label>
                    <input type="number" name="${inputPrefix}[${idx}][min]" class="w-full bg-white border border-slate-300 text-slate-900 text-sm rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none" placeholder="Min">
                </div>
                <div class="md:col-span-4">
                    <label class="block text-[10px] text-slate-500 uppercase tracking-wider mb-1 px-1">Max Amount</label>
                    <input type="number" name="${inputPrefix}[${idx}][max]" class="w-full bg-white border border-slate-300 text-slate-900 text-sm rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none" placeholder="Max">
                </div>
                <div class="md:col-span-4 flex items-end justify-between h-full pb-2">
                    <label class="flex items-center cursor-pointer select-none">
                        <input type="hidden" name="${inputPrefix}[${idx}][hide]" value="0">
                        <input type="checkbox" name="${inputPrefix}[${idx}][hide]" value="1" class="w-4 h-4 rounded border-slate-300 text-blue-600 bg-white focus:ring-blue-500">
                        <span class="ml-2 text-sm text-slate-700 group-hover:text-slate-900 transition-colors">Active</span>
                    </label>
                    <button type="button" class="text-slate-500 hover:text-red-600 p-1.5 rounded-md hover:bg-red-50 transition-all" onclick="removeRange(this)" title="Remove Range">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            container.appendChild(wrapper);
        }

        function addSalesRange(){
            addRange('sales-ranges', 'sales_ranges', 'empty-sales-ranges');
        }

        function addPurchaseRange(){
            addRange('purchase-ranges', 'purchase_ranges', 'empty-purchase-ranges');
        }

        function removeRange(btn){ 
            const row = btn.closest('.range-row');
            if(!row) return;
            const container = row.parentElement;
            row.remove();
            if(container && container.classList.contains('space-y-3') && container.querySelectorAll('.range-row').length === 0){
                container.parentElement.appendChild(createEmptyMessage(container.id));
            }
        }

        async function openRangeBills(minAmount, maxAmount, type = 'sale'){
            const df = document.getElementById('date_from')?.value || '';
            const dt = document.getElementById('date_to')?.value || '';
            const modal = document.getElementById('rangeModal');
            const body = document.getElementById('rangeModalBody');
            const title = document.getElementById('rangeModalTitle');
            if(title){
                title.textContent = (type === 'purchase') ? 'Purchases in Range' : 'Sales in Range';
            }
            body.innerHTML = '<div class="text-center text-slate-500">Loading…</div>';
            modal.classList.remove('hidden');
            try {
                const params = new URLSearchParams({min: String(minAmount), max: String(maxAmount), type: String(type || 'sale')});
                if(df) params.append('date_from', df);
                if(dt) params.append('date_to', dt);
                const res = await fetch('{{ route('information.range-bills') }}' + '?' + params.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}});
                const data = await res.json();
                body.innerHTML = data.html || '<div class="text-center text-slate-500">No data</div>';
            } catch (e) {
                body.innerHTML = '<div class="text-center text-red-600">Failed to load bills.</div>';
            }
        }
        function closeRangeModal(){
            document.getElementById('rangeModal').classList.add('hidden');
        }
    </script>
</body>
</html>

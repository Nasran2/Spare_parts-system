<div class="dl-table-wrap"><table class="dl-table sales-table">
    <thead><tr><th>Date</th><th>Invoice</th><th>Customer / Details</th><th class="dl-money">Cash</th><th class="dl-money">Cheque</th><th class="dl-money">Bank / Other</th><th class="dl-money">Credit</th><th class="dl-money">Total</th></tr></thead>
    <tbody>
        @forelse($salesRows as $row)<tr><td data-label="Date">{{ $row['date'] }}</td><td data-label="Invoice">@if($pdf){{ $row['invoice'] }}@else<a href="{{ $row['url'] }}">{{ $row['invoice'] }}</a>@endif</td><td data-label="Customer / Details" class="dl-description">{{ $row['customer'] }}@if($row['notes'])<p class="text-xs text-gray-500">{{ $row['notes'] }}</p>@endif</td>
            @foreach(['cash' => 'Cash', 'cheque' => 'Cheque', 'other' => 'Bank / Other', 'credit' => 'Credit', 'total' => 'Total'] as $field => $label)<td data-label="{{ $label }}" class="dl-money">{{ $maskMoney($row[$field]) }}</td>@endforeach
        </tr>@empty<tr><td colspan="8" class="dl-empty">No sales transactions found for this period.</td></tr>@endforelse
        <tr class="dl-balance"><td colspan="3">Period Totals · {{ $salesTotals['count'] }} transactions</td>@foreach(['cash', 'cheque', 'other', 'credit', 'total'] as $key)<td data-label="{{ ucfirst($key) }}" class="dl-money">{{ $maskMoney($salesTotals[$key]) }}</td>@endforeach</tr>
    </tbody>
</table></div>

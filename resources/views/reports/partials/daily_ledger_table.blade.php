<div class="dl-table-wrap"><table class="dl-table ledger-table">
    <thead><tr><th>Date</th><th>Type</th><th>Account</th><th>Related Account</th><th>Reference</th><th>Description & Details</th><th class="dl-money">Money In</th><th class="dl-money">Money Out</th><th class="dl-money">Balance</th></tr></thead>
    <tbody>
        <tr class="dl-balance"><td colspan="6">{{ $from }} · Balance Brought Forward</td><td></td><td></td><td data-label="Opening Balance" class="dl-money">{{ $maskMoney($section['opening']) }}</td></tr>
        @forelse($section['rows'] as $row)<tr>
            @foreach(['date' => 'Date', 'type' => 'Type', 'account' => 'Account', 'related' => 'Related Account', 'reference' => 'Reference', 'description' => 'Description & Details'] as $field => $label)<td data-label="{{ $label }}" class="{{ $field === 'description' ? 'dl-description' : '' }}">{{ $row[$field] ?: '—' }}</td>@endforeach
            <td data-label="Money In" class="dl-money dl-in">{{ $row['in'] ? $maskMoney($row['in']) : '—' }}</td><td data-label="Money Out" class="dl-money dl-out">{{ $row['out'] ? $maskMoney($row['out']) : '—' }}</td><td data-label="Balance" class="dl-money">{{ $maskMoney($row['balance']) }}</td>
        </tr>@empty<tr><td colspan="9" class="dl-empty">No transactions found for this period.</td></tr>@endforelse
        <tr class="dl-balance"><td colspan="6">{{ $to }} · Balance Carried Forward / Period Totals</td><td data-label="Total Money In" class="dl-money">{{ $maskMoney($section['in']) }}</td><td data-label="Total Money Out" class="dl-money">{{ $maskMoney($section['out']) }}</td><td data-label="Closing Balance" class="dl-money">{{ $maskMoney($section['closing']) }}</td></tr>
    </tbody>
</table></div>

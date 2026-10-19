@php
    $paymentBanks = \App\Models\Accounting\BankAccount::query()->where('is_active', true)
        ->whereHas('chartAccount', fn ($q) => $q->where('is_active', true))
        ->orderBy('bank_name')->get(['id', 'bank_name', 'account_name', 'account_number']);
@endphp
<script>
    window.paymentBankConfig = {
        accounts: @json($paymentBanks),
        addUrl: @json(route('accounting.banks').'#add-bank-account'),
        oldValues: @json(old()),
    };
</script>
<script src="{{ asset('js/payment-bank-accounts.js') }}?v=20261017"></script>

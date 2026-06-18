<?php

namespace App\Console\Commands;

use App\Services\ChequePaymentService;
use Illuminate\Console\Command;

class AutoPassChequePayments extends Command
{
    protected $signature = 'cheques:auto-pass';

    protected $description = 'Automatically pass pending POS cheque payments after the configured grace period.';

    public function handle(ChequePaymentService $service): int
    {
        $count = $service->autoPassEligible();
        $this->info("Auto-passed {$count} cheque payment(s).");

        return self::SUCCESS;
    }
}

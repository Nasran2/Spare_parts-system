<?php

namespace App\Services;

use App\Models\ChequePayment;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class ChequePaymentService
{
    public function pass(ChequePayment $cheque, ?int $userId = null, bool $autoPassed = false): ChequePayment
    {
        return DB::transaction(function () use ($cheque, $userId, $autoPassed) {
            $cheque = ChequePayment::lockForUpdate()->with('sale')->findOrFail($cheque->id);
            if ($cheque->status !== 'pending') {
                return $cheque;
            }

            $sale = Sale::lockForUpdate()->findOrFail($cheque->sale_id);
            $payment = Payment::create([
                'sale_id' => $sale->id,
                'customer_id' => $cheque->customer_id,
                'amount' => $cheque->amount,
                'payment_method' => 'cheque',
                'payment_date' => now()->toDateString(),
                'notes' => trim('Cheque passed: '.$cheque->cheque_number.($cheque->bank_name ? ' / '.$cheque->bank_name : '')),
            ]);

            $cheque->update([
                'payment_id' => $payment->id,
                'status' => 'passed',
                'processed_by' => $userId,
                'processed_at' => now(),
                'auto_passed' => $autoPassed,
            ]);

            $sale->held_cheque_amount = max(0, round((float) $sale->held_cheque_amount - (float) $cheque->amount, 2));
            $sale->paid_amount = min((float) $sale->total_amount, round((float) $sale->paid_amount + (float) $cheque->amount, 2));
            $this->refreshSaleBalance($sale);
            $sale->save();

            app(SalePaymentAccountingService::class)->recordChequePass($cheque, $sale, $userId);

            return $cheque->refresh();
        });
    }

    public function return(ChequePayment $cheque, ?int $userId = null): ChequePayment
    {
        return DB::transaction(function () use ($cheque, $userId) {
            $cheque = ChequePayment::lockForUpdate()->findOrFail($cheque->id);
            if ($cheque->status !== 'pending') {
                return $cheque;
            }

            $sale = Sale::lockForUpdate()->findOrFail($cheque->sale_id);
            $cheque->update([
                'status' => 'returned',
                'processed_by' => $userId,
                'processed_at' => now(),
                'auto_passed' => false,
            ]);

            $sale->held_cheque_amount = max(0, round((float) $sale->held_cheque_amount - (float) $cheque->amount, 2));
            $this->refreshSaleBalance($sale);
            $sale->save();

            return $cheque->refresh();
        });
    }

    public function autoPassEligible(?int $userId = null): int
    {
        if (! (bool) Setting::get('pos_cheque_auto_pass_enabled', true)) {
            return 0;
        }

        $days = max(0, (int) Setting::get('pos_cheque_auto_pass_days_after', 15));
        $cutoff = now()->subDays($days)->toDateString();
        $count = 0;

        ChequePayment::query()
            ->where('status', 'pending')
            ->whereDate('cheque_date', '<=', $cutoff)
            ->orderBy('cheque_date')
            ->chunkById(100, function ($cheques) use (&$count, $userId) {
                foreach ($cheques as $cheque) {
                    $this->pass($cheque, $userId, true);
                    $count++;
                }
            });

        return $count;
    }

    private function refreshSaleBalance(Sale $sale): void
    {
        $sale->due_amount = max(0, round((float) $sale->total_amount - (float) $sale->paid_amount - (float) $sale->held_cheque_amount, 2));
        $sale->payment_status = ((float) $sale->due_amount <= 0 && (float) $sale->held_cheque_amount <= 0)
            ? 'paid'
            : (((float) $sale->paid_amount > 0 || (float) $sale->held_cheque_amount > 0) ? 'partial' : 'unpaid');
    }
}

<?php

namespace App\Services;

use App\Models\ChequePayment;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class ChequePaymentService
{
    public function pass(ChequePayment $cheque, ?int $userId = null, bool $autoPassed = false, ?int $bankAccountId = null, ?int $supplierId = null): ChequePayment
    {
        return DB::transaction(function () use ($cheque, $userId, $autoPassed, $bankAccountId, $supplierId) {
            $cheque = ChequePayment::lockForUpdate()->with('sale')->findOrFail($cheque->id);
            if ($cheque->status !== 'pending') {
                return $cheque;
            }

            if ($supplierId && ($cheque->type === 'own' || ! $cheque->sale_id)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['supplier_id' => 'This cheque must be cleared through a bank account.']);
            }
            if ($supplierId && $bankAccountId) {
                throw \Illuminate\Validation\ValidationException::withMessages(['passed_bank_id' => 'Choose either a bank account or a supplier.']);
            }
            $bankAccountId = $supplierId ? null : ($bankAccountId ?: $cheque->bank_account_id);
            $bank = $supplierId ? null : \App\Support\PaymentBankAccount::resolve('bank_transfer', $bankAccountId, 'passed_bank_id');
            $bankAccountId = $bank?->id;
            if (! $cheque->sale_id) {
                return $this->passUnlinked($cheque, $userId, $autoPassed, $bankAccountId, $supplierId);
            }

            $sale = Sale::lockForUpdate()->findOrFail($cheque->sale_id);
            $payment = Payment::create([
                'sale_id' => $sale->id,
                'customer_id' => $cheque->customer_id,
                'user_id' => $userId,
                'amount' => $cheque->amount,
                'payment_method' => 'cheque',
                'bank_account_id' => $bankAccountId,
                'payment_date' => now()->toDateString(),
                'notes' => trim('Cheque passed: '.$cheque->cheque_number.($cheque->bank_name ? ' / '.$cheque->bank_name : '')),
            ]);

            $notes = $cheque->notes;
            if ($supplierId) {
                $supplier = \App\Models\Supplier::find($supplierId);
                $supplierName = $supplier ? $supplier->name : "ID: $supplierId";
                $notes = trim($notes."\nEndorsed to Supplier: ".$supplierName);

                // Create a Payment for the supplier to deduct their due
                \App\Models\Payment::create([
                    'supplier_id' => $supplierId,
                    'user_id' => $userId,
                    'amount' => $cheque->amount,
                    'payment_method' => 'cheque',
                    'payment_date' => now()->toDateString(),
                    'reference_no' => $cheque->cheque_number,
                    'notes' => trim('Endorsed customer cheque: '.$cheque->cheque_number.($cheque->bank_name ? ' / '.$cheque->bank_name : '')),
                ]);
            } elseif ($bankAccountId) {
                $bank = \App\Models\Accounting\BankAccount::find($bankAccountId);
                if ($bank) {
                    $notes = trim($notes."\nDeposited to Bank: ".$bank->bank_name.' - '.$bank->account_number);
                }
            }

            $cheque->update([
                'payment_id' => $payment->id,
                'status' => 'passed',
                'processed_by' => $userId,
                'processed_at' => now(),
                'auto_passed' => $autoPassed,
                'notes' => $notes,
                'bank_account_id' => $bankAccountId,
                'supplier_id' => $supplierId ?: $cheque->supplier_id,
            ]);

            $sale->held_cheque_amount = max(0, round((float) $sale->held_cheque_amount - (float) $cheque->amount, 2));
            $sale->paid_amount = min((float) $sale->total_amount, round((float) $sale->paid_amount + (float) $cheque->amount, 2));
            $this->refreshSaleBalance($sale);
            $sale->save();

            app(SalePaymentAccountingService::class)->recordChequePass($cheque, $sale, $userId, $bankAccountId, $supplierId);
            $this->syncPreOrder($sale);

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

            if (! $cheque->sale_id) {
                return $this->returnUnlinked($cheque, $userId);
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
            app(SalePaymentAccountingService::class)->reverseChequeHold(
                $cheque,
                $sale,
                $userId,
                'Returned cheque hold reversed'
            );
            $this->syncPreOrder($sale);

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
            ->where(function ($query) {
                $query->whereNotNull('bank_account_id');
                if (! \App\Models\Accounting\BankAccount::where('is_active', true)->exists()) {
                    $query->orWhereNull('bank_account_id');
                }
            })
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

    private function passUnlinked(ChequePayment $cheque, ?int $userId, bool $autoPassed, ?int $bankId, ?int $supplierId): ChequePayment
    {
        $own = $cheque->type === 'own';
        if ($supplierId && ! $own) {
            throw \Illuminate\Validation\ValidationException::withMessages(['supplier_id' => 'Deposit advance and preorder cheques to a bank account.']);
        }
        $payment = $cheque->payment_id ? Payment::find($cheque->payment_id) : null;
        if (! $payment) {
            $payment = Payment::create([
                'customer_id' => $own ? null : $cheque->customer_id,
                'supplier_id' => $own ? $cheque->supplier_id : null,
                'purchase_id' => $own ? $cheque->purchase_id : null,
                'pre_order_id' => $own ? null : $cheque->pre_order_id,
                'bank_account_id' => $bankId,
                'user_id' => $userId,
                'amount' => $cheque->amount,
                'payment_method' => 'cheque',
                'payment_date' => now()->toDateString(),
                'reference_no' => $cheque->cheque_number,
                'notes' => 'Cleared cheque: '.$cheque->cheque_number,
            ]);
            if ($own) {
                app(BulkPaymentAccountingService::class)->recordSupplierPayment($payment, $userId);
            } elseif ($cheque->pre_order_id) {
                app(SalePaymentAccountingService::class)->recordPreOrderPayment($payment, $cheque->preOrder, $userId);
            } else {
                app(BulkPaymentAccountingService::class)->recordCustomerPayment($payment, $userId);
            }
        }
        if ($own && $cheque->purchase_id) {
            $purchase = \App\Models\Purchase::lockForUpdate()->findOrFail($cheque->purchase_id);
            $purchase->held_own_cheque_amount = max(0, (float) $purchase->held_own_cheque_amount - (float) $cheque->amount);
            $purchase->paid_amount = (float) $purchase->paid_amount + (float) $cheque->amount;
            $purchase->due_amount = max(0, (float) $purchase->total_amount - (float) $purchase->paid_amount - (float) $purchase->held_own_cheque_amount);
            $purchase->payment_status = $purchase->due_amount <= 0 && $purchase->held_own_cheque_amount <= 0 ? 'paid' : 'partial';
            $purchase->save();
        } elseif ($cheque->pre_order_id) {
            $preOrder = \App\Models\PreOrder::lockForUpdate()->findOrFail($cheque->pre_order_id);
            $preOrder->held_cheque_amount = max(0, (float) $preOrder->held_cheque_amount - (float) $cheque->amount);
            $preOrder->paid_amount = (float) $preOrder->paid_amount + (float) $cheque->amount;
            $preOrder->due_amount = max(0, (float) $preOrder->grand_total - (float) $preOrder->paid_amount - (float) $preOrder->held_cheque_amount);
            $preOrder->payment_status = $preOrder->due_amount <= 0 && $preOrder->held_cheque_amount <= 0 ? 'paid' : 'partial';
            $preOrder->save();
            app(SalePaymentAccountingService::class)->reversePreOrderChequeHold($cheque, $preOrder, $userId, 'Cheque cleared');
        }
        $cheque->update(['payment_id' => $payment->id, 'bank_account_id' => $bankId, 'status' => 'passed', 'processed_by' => $userId, 'processed_at' => now(), 'auto_passed' => $autoPassed]);

        return $cheque->refresh();
    }

    private function returnUnlinked(ChequePayment $cheque, ?int $userId): ChequePayment
    {
        if ($cheque->type === 'own' && $cheque->purchase_id) {
            $purchase = \App\Models\Purchase::lockForUpdate()->findOrFail($cheque->purchase_id);
            $purchase->held_own_cheque_amount = max(0, (float) $purchase->held_own_cheque_amount - (float) $cheque->amount);
            $purchase->due_amount = max(0, (float) $purchase->total_amount - (float) $purchase->paid_amount - (float) $purchase->held_own_cheque_amount);
            $purchase->payment_status = $purchase->paid_amount > 0 || $purchase->held_own_cheque_amount > 0 ? 'partial' : 'unpaid';
            $purchase->save();
        } elseif ($cheque->pre_order_id) {
            $preOrder = \App\Models\PreOrder::lockForUpdate()->findOrFail($cheque->pre_order_id);
            $preOrder->held_cheque_amount = max(0, (float) $preOrder->held_cheque_amount - (float) $cheque->amount);
            $preOrder->due_amount = max(0, (float) $preOrder->grand_total - (float) $preOrder->paid_amount - (float) $preOrder->held_cheque_amount);
            $preOrder->payment_status = $preOrder->paid_amount > 0 || $preOrder->held_cheque_amount > 0 ? 'partial' : 'unpaid';
            $preOrder->save();
            app(SalePaymentAccountingService::class)->reversePreOrderChequeHold($cheque, $preOrder, $userId, 'Cheque returned');
        }
        $cheque->update(['status' => 'returned', 'processed_by' => $userId, 'processed_at' => now(), 'auto_passed' => false]);

        return $cheque->refresh();
    }

    private function refreshSaleBalance(Sale $sale): void
    {
        $sale->due_amount = max(0, round((float) $sale->total_amount - (float) $sale->paid_amount - (float) $sale->held_cheque_amount, 2));
        $sale->payment_status = ((float) $sale->due_amount <= 0 && (float) $sale->held_cheque_amount <= 0)
            ? 'paid'
            : (((float) $sale->paid_amount > 0 || (float) $sale->held_cheque_amount > 0) ? 'partial' : 'unpaid');
    }

    private function syncPreOrder(Sale $sale): void
    {
        \App\Models\PreOrder::query()->where('sale_id', $sale->id)->update([
            'paid_amount' => $sale->paid_amount,
            'held_cheque_amount' => $sale->held_cheque_amount,
            'due_amount' => $sale->due_amount,
            'payment_status' => $sale->payment_status,
            'updated_at' => now(),
        ]);
    }
}

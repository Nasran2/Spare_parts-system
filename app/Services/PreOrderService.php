<?php

namespace App\Services;

use App\Models\ChequePayment;
use App\Models\Payment;
use App\Models\PreOrder;
use App\Models\PreOrderActivity;
use App\Models\PreOrderItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\StoreStock;
use App\Models\TaxSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreOrderService
{
    public function save(array $data, int $userId, ?PreOrder $preOrder = null): PreOrder
    {
        return DB::transaction(function () use ($data, $userId, $preOrder) {
            $creating = ! $preOrder;
            if ($preOrder) {
                $preOrder = PreOrder::lockForUpdate()->findOrFail($preOrder->id);
                if ($preOrder->status !== 'pending') {
                    throw ValidationException::withMessages(['status' => 'Only pending Pre-Orders can be edited.']);
                }
            } else {
                $preOrder = new PreOrder;
                $preOrder->created_by = $userId;
                $preOrder->status = 'pending';
                $preOrder->payment_status = 'unpaid';
            }

            $old = $preOrder->exists ? $preOrder->only([
                'customer_id', 'store_id', 'pre_order_date', 'document_type', 'vehicle_name',
                'registration_number', 'chassis_number', 'expected_delivery_date', 'grand_total',
            ]) : null;

            $preOrder->fill(Arr::only($data, [
                'customer_id', 'store_id', 'pre_order_date', 'document_type', 'vehicle_name',
                'registration_number', 'chassis_number', 'vehicle_description', 'vehicle_image',
                'instructions', 'notes', 'expected_delivery_date', 'bill_discount_type',
                'bill_discount_value',
            ]));
            $preOrder->updated_by = $userId;
            $preOrder->save();

            $calculated = $this->calculateInputItems(
                $data['items'],
                $data['bill_discount_type'] ?? 'fixed',
                $data['bill_discount_value'] ?? 0
            );

            $oldItems = $preOrder->items()->get()->map(fn ($item) => $item->only([
                'id', 'product_id', 'original_product_name', 'quantity', 'quoted_price', 'final_price',
            ]))->values()->all();
            $preOrder->items()->delete();
            foreach ($calculated['items'] as $itemData) {
                $preOrder->items()->create($itemData);
            }
            $newItems = $preOrder->items()->get();

            if (! $creating) {
                $oldNames = collect($oldItems)->pluck('original_product_name')->countBy();
                $newNames = $newItems->pluck('original_product_name')->countBy();
                $added = $newNames->flatMap(fn ($count, $name) => array_fill(0, max(0, $count - ($oldNames[$name] ?? 0)), $name))->values()->all();
                $removed = $oldNames->flatMap(fn ($count, $name) => array_fill(0, max(0, $count - ($newNames[$name] ?? 0)), $name))->values()->all();
                if ($added) {
                    $this->activity($preOrder, $userId, 'product_added', 'Product item(s) added: '.implode(', ', $added).'.', null, ['items' => $added]);
                }
                if ($removed) {
                    $this->activity($preOrder, $userId, 'product_removed', 'Product item(s) removed: '.implode(', ', $removed).'.', ['items' => $removed], null);
                }
            }

            $preOrder->fill([
                'subtotal' => $calculated['totals']['gross'],
                'discount_amount' => $calculated['totals']['discount'],
                'tax_amount' => $calculated['totals']['vat'],
                'rounding_adjustment' => $calculated['totals']['rounding_adjustment'],
                'grand_total' => $calculated['totals']['total'],
                'due_amount' => $calculated['totals']['total'],
                'paid_amount' => 0,
                'held_cheque_amount' => 0,
                'payment_status' => 'unpaid',
            ])->save();

            $this->activity(
                $preOrder,
                $userId,
                $creating ? 'created' : 'edited',
                $creating ? 'Pre-Order created.' : 'Pre-Order details and items updated.',
                $old ? array_merge($old, ['items' => $oldItems]) : null,
                array_merge($preOrder->only([
                    'customer_id', 'store_id', 'pre_order_date', 'document_type', 'vehicle_name',
                    'registration_number', 'chassis_number', 'expected_delivery_date', 'grand_total',
                ]), ['items' => $newItems->toArray()])
            );

            return $preOrder->fresh(['items.product', 'customer', 'store']);
        });
    }

    public function syncProduct(PreOrder $preOrder, PreOrderItem $item, Product $product, ?int $productPriceId, int $userId, string $priceAction = 'keep', ?float $customPrice = null): PreOrder
    {
        return DB::transaction(function () use ($preOrder, $item, $product, $productPriceId, $userId, $priceAction, $customPrice) {
            $preOrder = PreOrder::lockForUpdate()->findOrFail($preOrder->id);
            $item = PreOrderItem::lockForUpdate()->where('pre_order_id', $preOrder->id)->findOrFail($item->id);
            if ($preOrder->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Products can only be linked on pending Pre-Orders.']);
            }

            $price = null;
            if ($productPriceId) {
                $price = ProductPrice::query()->whereKey($productPriceId)->where('product_id', $product->id)->first();
                if (! $price) {
                    throw ValidationException::withMessages(['product_price_id' => 'The selected price does not belong to this product.']);
                }
            } else {
                $price = $product->activePrices()->where('is_default', true)->first()
                    ?: $product->activePrices()->first();
            }

            $old = $item->only(['product_id', 'product_price_id', 'quoted_price', 'final_price', 'sync_status']);
            $newPrice = (float) $item->final_price;
            if ($priceAction === 'current') {
                $newPrice = (float) ($price?->selling_price ?? $product->selling_price);
            } elseif ($priceAction === 'custom') {
                if ($customPrice === null || $customPrice < 0) {
                    throw ValidationException::withMessages(['custom_price' => 'Enter a valid new price.']);
                }
                $newPrice = $customPrice;
            }

            $item->update([
                'product_id' => $product->id,
                'product_price_id' => $price?->id,
                'final_price' => $newPrice,
                'sync_status' => 'linked',
            ]);
            $this->recalculate($preOrder);
            $this->activity($preOrder, $userId, 'product_synced',
                'Linked “'.$item->original_product_name.'” to product “'.$product->name.'”.',
                $old,
                $item->fresh()->only(['product_id', 'product_price_id', 'quoted_price', 'final_price', 'sync_status'])
            );

            return $preOrder->fresh(['items.product.activePrices']);
        });
    }

    public function changePrice(PreOrder $preOrder, PreOrderItem $item, string $action, ?float $customPrice, int $userId): PreOrder
    {
        return DB::transaction(function () use ($preOrder, $item, $action, $customPrice, $userId) {
            $preOrder = PreOrder::lockForUpdate()->findOrFail($preOrder->id);
            $item = PreOrderItem::lockForUpdate()->where('pre_order_id', $preOrder->id)->findOrFail($item->id);
            if ($preOrder->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Prices can only be changed on pending Pre-Orders.']);
            }

            $oldPrice = (float) $item->final_price;
            $newPrice = match ($action) {
                'keep' => (float) $item->quoted_price,
                'current' => $this->currentSellingPrice($item),
                'custom' => $customPrice,
                default => null,
            };
            if ($newPrice === null || $newPrice < 0) {
                throw ValidationException::withMessages(['custom_price' => 'Enter a valid price.']);
            }

            $item->update(['final_price' => $newPrice]);
            $this->recalculate($preOrder);
            $this->activity($preOrder, $userId, 'price_changed',
                'Price changed for “'.$item->original_product_name.'”.',
                ['final_price' => $oldPrice],
                ['final_price' => $newPrice, 'action' => $action]
            );

            return $preOrder->fresh(['items.product']);
        });
    }

    public function cancel(PreOrder $preOrder, ?string $reason, int $userId): PreOrder
    {
        return DB::transaction(function () use ($preOrder, $reason, $userId) {
            $preOrder = PreOrder::lockForUpdate()->findOrFail($preOrder->id);
            if ($preOrder->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending Pre-Orders can be cancelled.']);
            }
            $preOrder->update([
                'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $userId,
                'cancellation_reason' => $reason, 'updated_by' => $userId,
            ]);
            $this->activity($preOrder, $userId, 'cancelled', 'Pre-Order cancelled.'.($reason ? ' Reason: '.$reason : ''), ['status' => 'pending'], ['status' => 'cancelled']);

            return $preOrder->fresh();
        });
    }

    public function reopen(PreOrder $preOrder, ?string $reason, int $userId): PreOrder
    {
        return DB::transaction(function () use ($preOrder, $reason, $userId) {
            $preOrder = PreOrder::lockForUpdate()->findOrFail($preOrder->id);
            if ($preOrder->status === 'cancelled') {
                $preOrder->update([
                    'status' => 'pending', 'cancelled_at' => null, 'cancelled_by' => null,
                    'cancellation_reason' => null, 'updated_by' => $userId,
                ]);
                $this->activity($preOrder, $userId, 'reopened', 'Cancelled Pre-Order reopened.'.($reason ? ' Reason: '.$reason : ''), ['status' => 'cancelled'], ['status' => 'pending']);

                return $preOrder->fresh();
            }

            if ($preOrder->status !== 'completed' || ! $preOrder->sale_id) {
                throw ValidationException::withMessages(['status' => 'This Pre-Order cannot be reopened.']);
            }

            $sale = Sale::withoutGlobalScopes()->lockForUpdate()->with(['items', 'payments', 'chequePayments', 'returns'])->findOrFail($preOrder->sale_id);
            if ($sale->returns->isNotEmpty()) {
                throw ValidationException::withMessages(['status' => 'Reverse linked sale returns before reopening this Pre-Order.']);
            }

            $paymentSnapshot = $sale->payments->map->only(['amount', 'payment_method', 'payment_date', 'reference_no', 'notes'])->all();
            $chequeSnapshot = $sale->chequePayments->map->only(['amount', 'cheque_number', 'bank_name', 'cheque_date', 'status'])->all();
            $accounting = app(SalePaymentAccountingService::class);
            foreach ($sale->payments as $payment) {
                $accounting->reversePayment($payment, $sale, $userId, 'Pre-Order completion reversed');
            }
            foreach ($sale->chequePayments as $cheque) {
                $accounting->reverseCheque($cheque, $sale, $userId, 'Pre-Order completion reversed');
            }

            app(TaxPostingService::class)->reverse('sale', $sale->id, 'Pre-Order completion reversed');
            foreach ($sale->items as $saleItem) {
                $this->restoreStock($sale, $saleItem);
            }

            $saleNumber = $sale->sale_no;
            $preOrder->update(['sale_id' => null]);
            $sale->delete();
            $preOrder->update([
                'status' => 'pending', 'payment_status' => 'unpaid', 'paid_amount' => 0,
                'held_cheque_amount' => 0, 'due_amount' => $preOrder->grand_total,
                'completed_at' => null, 'completed_by' => null, 'updated_by' => $userId,
            ]);
            $this->activity($preOrder, $userId, 'completion_reversed',
                'Completed Pre-Order reopened and sale '.$saleNumber.' safely reversed.'.($reason ? ' Reason: '.$reason : ''),
                ['status' => 'completed', 'sale_no' => $saleNumber, 'payments' => $paymentSnapshot, 'cheques' => $chequeSnapshot],
                ['status' => 'pending', 'sale_id' => null]
            );

            return $preOrder->fresh();
        });
    }

    public function complete(PreOrder $preOrder, array $payments, int $userId): PreOrder
    {
        return DB::transaction(function () use ($preOrder, $payments, $userId) {
            $preOrder = PreOrder::lockForUpdate()->with(['items.product.taxSetting', 'customer', 'store'])->findOrFail($preOrder->id);
            if ($preOrder->status !== 'pending' || $preOrder->sale_id) {
                throw ValidationException::withMessages(['status' => 'This Pre-Order has already been completed or is not pending.']);
            }
            if ($preOrder->items->isEmpty() || $preOrder->items->contains(fn ($item) => ! $item->product_id)) {
                throw ValidationException::withMessages(['items' => 'Link every Pre-Order item to an actual product before completion.']);
            }

            $normalized = $this->normalizePayments($payments, (float) $preOrder->grand_total);
            $cashPaid = collect($normalized)->where('method', '!=', 'cheque')->sum('amount');
            $held = collect($normalized)->where('method', 'cheque')->sum('amount');
            $due = max(0, round((float) $preOrder->grand_total - $cashPaid - $held, 2));

            foreach ($preOrder->items as $item) {
                $this->assertStockAvailable($preOrder, $item);
            }

            $firstMethod = $normalized[0]['method'] ?? 'credit';
            $sale = Sale::create([
                'customer_id' => $preOrder->customer_id,
                'user_id' => $userId,
                'store_id' => $preOrder->store_id,
                'sale_date' => now()->toDateString(),
                'subtotal' => $preOrder->subtotal,
                'tax' => $preOrder->tax_amount,
                'rounding_adjustment' => $preOrder->rounding_adjustment,
                'tax_snapshot' => TaxSetting::current()->snapshot(),
                'discount' => $preOrder->discount_amount,
                'total_amount' => $preOrder->grand_total,
                'paid_amount' => $cashPaid,
                'held_cheque_amount' => $held,
                'tendered_amount' => $cashPaid + $held,
                'due_amount' => $due,
                'payment_status' => ($due <= 0 && $held <= 0) ? 'paid' : (($cashPaid > 0 || $held > 0) ? 'partial' : 'unpaid'),
                'payment_method' => $firstMethod,
                'sale_type' => 'sale',
                'notes' => trim('Completed from '.$preOrder->pre_order_number.'. '.$preOrder->notes),
            ]);

            $taxPairs = [];
            foreach ($preOrder->items as $item) {
                $saleItem = SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item->product_id,
                    'product_price_id' => $item->product_price_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->final_price,
                    'total' => $item->line_total,
                ]);
                $taxPairs[] = ['model' => $saleItem, 'tax' => $item->tax_snapshot];
                $this->deductStock($preOrder, $item);
            }

            $accounting = app(SalePaymentAccountingService::class);
            foreach ($normalized as $paymentData) {
                if ($paymentData['method'] === 'cheque') {
                    $cheque = ChequePayment::create([
                        'sale_id' => $sale->id,
                        'customer_id' => $preOrder->customer_id,
                        'user_id' => $userId,
                        'cheque_date' => $paymentData['cheque_date'],
                        'cheque_number' => $paymentData['cheque_number'],
                        'bank_name' => $paymentData['bank_name'] ?? null,
                        'account_name' => $paymentData['account_name'] ?? null,
                        'amount' => $paymentData['amount'],
                        'status' => 'pending',
                        'notes' => $paymentData['notes'] ?? null,
                    ]);
                    $accounting->recordChequeHold($cheque, $sale, $userId);
                } else {
                    $payment = Payment::create([
                        'sale_id' => $sale->id,
                        'customer_id' => $preOrder->customer_id,
                        'user_id' => $userId,
                        'amount' => $paymentData['amount'],
                        'payment_method' => $paymentData['method'],
                        'reference_no' => $paymentData['reference'] ?? null,
                        'payment_date' => $paymentData['date'] ?? now()->toDateString(),
                        'notes' => $paymentData['notes'] ?? null,
                        'store_id' => $preOrder->store_id,
                    ]);
                    $accounting->recordSalePayment($payment, $sale, $userId);
                }
            }

            $sale->loadMissing(['customer', 'store']);
            app(TaxPostingService::class)->postSale($sale, $taxPairs, TaxSetting::current($sale->sale_date));
            $preOrder->update([
                'sale_id' => $sale->id, 'status' => 'completed', 'completed_at' => now(),
                'completed_by' => $userId, 'paid_amount' => $sale->paid_amount,
                'held_cheque_amount' => $sale->held_cheque_amount, 'due_amount' => $sale->due_amount,
                'payment_status' => $sale->payment_status, 'updated_by' => $userId,
            ]);
            $this->activity($preOrder, $userId, 'completed',
                'Pre-Order completed as sale '.$sale->sale_no.'.',
                ['status' => 'pending'],
                ['status' => 'completed', 'sale_id' => $sale->id, 'sale_no' => $sale->sale_no]
            );

            return $preOrder->fresh(['sale.payments', 'sale.chequePayments', 'items.product']);
        }, 3);
    }

    public function addPayment(PreOrder $preOrder, array $data, int $userId): PreOrder
    {
        return DB::transaction(function () use ($preOrder, $data, $userId) {
            $preOrder = PreOrder::lockForUpdate()->findOrFail($preOrder->id);
            if ($preOrder->status !== 'completed' || ! $preOrder->sale_id) {
                throw ValidationException::withMessages(['status' => 'Payments can only be collected for completed Pre-Orders.']);
            }
            $sale = Sale::withoutGlobalScopes()->lockForUpdate()->findOrFail($preOrder->sale_id);
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0 || $amount > (float) $sale->due_amount) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the remaining due amount.']);
            }

            if ($data['payment_method'] === 'cheque') {
                $cheque = ChequePayment::create([
                    'sale_id' => $sale->id, 'customer_id' => $sale->customer_id, 'user_id' => $userId,
                    'cheque_date' => $data['cheque_date'], 'cheque_number' => $data['cheque_number'],
                    'bank_name' => $data['bank_name'] ?? null, 'account_name' => $data['account_name'] ?? null,
                    'amount' => $amount, 'status' => 'pending', 'notes' => $data['notes'] ?? null,
                ]);
                $sale->held_cheque_amount = round((float) $sale->held_cheque_amount + $amount, 2);
                app(SalePaymentAccountingService::class)->recordChequeHold($cheque, $sale, $userId);
            } else {
                $payment = Payment::create([
                    'sale_id' => $sale->id, 'customer_id' => $sale->customer_id, 'user_id' => $userId,
                    'amount' => $amount, 'payment_method' => $data['payment_method'],
                    'reference_no' => $data['reference_no'] ?? null, 'payment_date' => $data['payment_date'],
                    'notes' => $data['notes'] ?? null, 'store_id' => $sale->store_id,
                ]);
                $sale->paid_amount = round((float) $sale->paid_amount + $amount, 2);
                app(SalePaymentAccountingService::class)->recordSalePayment($payment, $sale, $userId);
            }
            $this->refreshSaleBalance($sale);
            $sale->save();
            $this->syncFinancials($preOrder, $sale);
            $this->activity($preOrder, $userId, 'payment_added',
                'Payment of Rs '.number_format($amount, 2).' added via '.str_replace('_', ' ', $data['payment_method']).'.',
                null,
                ['amount' => $amount, 'method' => $data['payment_method'], 'sale_id' => $sale->id]
            );

            return $preOrder->fresh(['sale.payments.user', 'sale.chequePayments.user']);
        });
    }

    public function deletePayment(PreOrder $preOrder, Payment $payment, int $userId): PreOrder
    {
        return DB::transaction(function () use ($preOrder, $payment, $userId) {
            $preOrder = PreOrder::lockForUpdate()->findOrFail($preOrder->id);
            $sale = Sale::withoutGlobalScopes()->lockForUpdate()->findOrFail($preOrder->sale_id);
            $payment = Payment::lockForUpdate()->where('sale_id', $sale->id)->findOrFail($payment->id);
            if (ChequePayment::where('payment_id', $payment->id)->exists()) {
                throw ValidationException::withMessages(['payment' => 'Passed cheque payments must be managed through Cheque Management.']);
            }
            app(SalePaymentAccountingService::class)->reversePayment($payment, $sale, $userId, 'Pre-Order payment deleted');
            $amount = (float) $payment->amount;
            $snapshot = $payment->only(['amount', 'payment_method', 'payment_date', 'reference_no', 'notes']);
            $payment->delete();
            $sale->paid_amount = max(0, round((float) $sale->paid_amount - $amount, 2));
            $this->refreshSaleBalance($sale);
            $sale->save();
            $this->syncFinancials($preOrder, $sale);
            $this->activity($preOrder, $userId, 'payment_removed', 'Payment removed and accounting reversed.', $snapshot, null);

            return $preOrder->fresh(['sale.payments', 'sale.chequePayments']);
        });
    }

    public function currentStock(Product $product, ?int $storeId, ?int $productPriceId = null): float
    {
        $available = [(float) $product->stock_quantity];
        $usePriceWise = (bool) Setting::get('use_price_wise_stock', true);
        if ($usePriceWise && $productPriceId) {
            $priceStock = ProductPrice::whereKey($productPriceId)->value('stock_qty');
            if ($priceStock !== null) {
                $available[] = (float) $priceStock;
            }
        }
        if ($storeId) {
            $query = StoreStock::withoutGlobalScopes()->where('store_id', $storeId)->where('product_id', $product->id);
            if ($usePriceWise && $productPriceId) {
                $stock = (clone $query)->where('product_price_id', $productPriceId)->value('quantity');
                if ($stock !== null) {
                    $available[] = (float) $stock;
                } else {
                    $fallback = (clone $query)->whereNull('product_price_id')->value('quantity');
                    $available[] = (float) ($fallback ?? 0);
                }
            } else {
                $stock = (clone $query)->whereNull('product_price_id')->value('quantity');
                $available[] = (float) ($stock ?? 0);
            }
        }

        return min($available);
    }

    public function currentSellingPrice(PreOrderItem $item): float
    {
        if ($item->productPrice) {
            return (float) $item->productPrice->selling_price;
        }

        return (float) ($item->product?->selling_price ?? $item->final_price);
    }

    private function calculateInputItems(array $items, string $billDiscountType, float|string $billDiscountValue): array
    {
        $calculator = app(TaxCalculationService::class);
        $settings = TaxSetting::current();
        $inputs = [];
        $normalized = [];

        foreach ($items as $item) {
            $product = ! empty($item['product_id'])
                ? Product::with('taxSetting')->whereKey($item['product_id'])->where('is_active', true)->first()
                : null;
            if (! empty($item['product_id']) && ! $product) {
                throw ValidationException::withMessages(['items' => 'One of the selected products is not active.']);
            }
            $rules = $calculator->productRules($product, $settings, 'sale');
            $inputs[] = [
                'unit_price' => (string) $item['unit_price'],
                'quantity' => (string) $item['quantity'],
                'line_discount_type' => $item['discount_type'] === 'percentage' ? 'percentage' : 'fixed',
                'line_discount_value' => (string) $item['discount_value'],
                'tax_status' => $rules['tax_status'], 'vat_rate' => $rules['vat_rate'],
                'price_mode' => $rules['price_mode'], 'vat_allowed' => $rules['vat_allowed'],
                'vat_enabled' => $settings->vat_enabled,
            ];
            $normalized[] = compact('item', 'product');
        }

        $invoice = $calculator->calculateInvoice($inputs, $billDiscountType, $billDiscountValue);
        $resultItems = [];
        foreach ($normalized as $index => $entry) {
            $item = $entry['item'];
            $tax = $invoice['lines'][$index];
            $resultItems[] = [
                'product_id' => $entry['product']?->id,
                'product_price_id' => $item['product_price_id'] ?? null,
                'original_product_name' => trim((string) $item['original_product_name']),
                'description' => $item['description'] ?? null,
                'quantity' => (int) $item['quantity'],
                'quoted_price' => round((float) ($item['quoted_price'] ?? $item['unit_price']), 2),
                'final_price' => round((float) $item['unit_price'], 2),
                'discount_type' => $item['discount_type'],
                'discount_value' => round((float) $item['discount_value'], 2),
                'gross_amount' => $tax['gross_amount'], 'discount_amount' => $tax['discount_amount'],
                'tax_amount' => $tax['vat_amount'], 'line_total' => $tax['total_amount'],
                'sync_status' => $entry['product'] ? 'linked' : 'unlinked',
                'tax_snapshot' => $tax,
                'notes' => $item['notes'] ?? null,
            ];
        }

        return ['items' => $resultItems, 'totals' => $invoice['totals']];
    }

    private function recalculate(PreOrder $preOrder): void
    {
        $preOrder->load('items.product.taxSetting');
        $calculator = app(TaxCalculationService::class);
        $settings = TaxSetting::current();
        $inputs = [];
        foreach ($preOrder->items as $item) {
            $rules = $calculator->productRules($item->product, $settings, 'sale');
            $inputs[] = [
                'unit_price' => (string) $item->final_price, 'quantity' => (string) $item->quantity,
                'line_discount_type' => $item->discount_type, 'line_discount_value' => (string) $item->discount_value,
                'tax_status' => $rules['tax_status'], 'vat_rate' => $rules['vat_rate'],
                'price_mode' => $rules['price_mode'], 'vat_allowed' => $rules['vat_allowed'],
                'vat_enabled' => $settings->vat_enabled,
            ];
        }
        $invoice = $calculator->calculateInvoice($inputs, $preOrder->bill_discount_type, $preOrder->bill_discount_value);
        foreach ($preOrder->items as $index => $item) {
            $tax = $invoice['lines'][$index];
            $item->update([
                'gross_amount' => $tax['gross_amount'], 'discount_amount' => $tax['discount_amount'],
                'tax_amount' => $tax['vat_amount'], 'line_total' => $tax['total_amount'], 'tax_snapshot' => $tax,
            ]);
        }
        $preOrder->update([
            'subtotal' => $invoice['totals']['gross'], 'discount_amount' => $invoice['totals']['discount'],
            'tax_amount' => $invoice['totals']['vat'], 'rounding_adjustment' => $invoice['totals']['rounding_adjustment'],
            'grand_total' => $invoice['totals']['total'], 'due_amount' => $invoice['totals']['total'],
        ]);
    }

    private function normalizePayments(array $payments, float $total): array
    {
        $normalized = [];
        $sum = 0;
        foreach ($payments as $index => $payment) {
            $method = $payment['method'] ?? 'due';
            $amount = round((float) ($payment['amount'] ?? 0), 2);
            if ($method === 'due' || $amount <= 0) {
                continue;
            }
            if (! in_array($method, ['cash', 'bank_deposit', 'bank_transfer', 'card', 'mobile_payment', 'cheque'], true)) {
                throw ValidationException::withMessages(["payments.$index.method" => 'Invalid payment method.']);
            }
            if ($method === 'cheque' && (empty($payment['cheque_number']) || empty($payment['cheque_date']))) {
                throw ValidationException::withMessages(["payments.$index.cheque_number" => 'Cheque number and date are required.']);
            }
            $sum += $amount;
            $normalized[] = array_merge($payment, ['method' => $method, 'amount' => $amount]);
        }
        if (round($sum, 2) > round($total, 2)) {
            throw ValidationException::withMessages(['payments' => 'Payments cannot exceed the invoice total.']);
        }

        return $normalized;
    }

    private function assertStockAvailable(PreOrder $preOrder, PreOrderItem $item): void
    {
        $available = $this->currentStock($item->product, $preOrder->store_id, $item->product_price_id);
        if ($available < (float) $item->quantity) {
            throw ValidationException::withMessages(['items' => $item->original_product_name.' has only '.$available.' available; '.$item->quantity.' required.']);
        }
    }

    private function deductStock(PreOrder $preOrder, PreOrderItem $item): void
    {
        $product = Product::lockForUpdate()->findOrFail($item->product_id);
        if ((float) $product->stock_quantity < (float) $item->quantity) {
            throw ValidationException::withMessages(['items' => 'Insufficient total stock for '.$item->original_product_name.'.']);
        }
        if ((bool) Setting::get('use_price_wise_stock', true) && $item->product_price_id) {
            $price = ProductPrice::lockForUpdate()->findOrFail($item->product_price_id);
            if ((float) $price->stock_qty < (float) $item->quantity) {
                throw ValidationException::withMessages(['items' => 'Insufficient price-level stock for '.$item->original_product_name.'.']);
            }
            $price->decrement('stock_qty', $item->quantity);
        }
        if ($preOrder->store_id) {
            $stockQuery = StoreStock::withoutGlobalScopes()->lockForUpdate()
                ->where('store_id', $preOrder->store_id)->where('product_id', $item->product_id)
                ->where('product_price_id', (bool) Setting::get('use_price_wise_stock', true) ? $item->product_price_id : null);
            $stock = $stockQuery->first();
            if (! $stock && (bool) Setting::get('use_price_wise_stock', true) && $item->product_price_id) {
                $stock = StoreStock::withoutGlobalScopes()->lockForUpdate()
                    ->where('store_id', $preOrder->store_id)->where('product_id', $item->product_id)
                    ->whereNull('product_price_id')->first();
            }
            if (! $stock || (float) $stock->quantity < (float) $item->quantity) {
                throw ValidationException::withMessages(['items' => 'Insufficient store stock for '.$item->original_product_name.'.']);
            }
            $stock->decrement('quantity', $item->quantity);
        }
        $product->decrement('stock_quantity', $item->quantity);
        $product->refresh();
        StockAlertService::check($product);
    }

    private function restoreStock(Sale $sale, SaleItem $item): void
    {
        Product::whereKey($item->product_id)->increment('stock_quantity', $item->quantity);
        if ((bool) Setting::get('use_price_wise_stock', true) && $item->product_price_id) {
            ProductPrice::whereKey($item->product_price_id)->increment('stock_qty', $item->quantity);
        }
        if ($sale->store_id) {
            $priceId = (bool) Setting::get('use_price_wise_stock', true) ? $item->product_price_id : null;
            $stock = StoreStock::withoutGlobalScopes()->where('store_id', $sale->store_id)
                ->where('product_id', $item->product_id)->where('product_price_id', $priceId)->first();
            if (! $stock && $priceId) {
                $stock = StoreStock::withoutGlobalScopes()->where('store_id', $sale->store_id)
                    ->where('product_id', $item->product_id)->whereNull('product_price_id')->first();
            }
            $stock ??= StoreStock::withoutGlobalScopes()->create([
                'store_id' => $sale->store_id, 'product_id' => $item->product_id,
                'product_price_id' => $priceId, 'quantity' => 0,
            ]);
            $stock->increment('quantity', $item->quantity);
        }
    }

    private function refreshSaleBalance(Sale $sale): void
    {
        $sale->due_amount = max(0, round((float) $sale->total_amount - (float) $sale->paid_amount - (float) $sale->held_cheque_amount, 2));
        $sale->payment_status = ((float) $sale->due_amount <= 0 && (float) $sale->held_cheque_amount <= 0)
            ? 'paid'
            : (((float) $sale->paid_amount > 0 || (float) $sale->held_cheque_amount > 0) ? 'partial' : 'unpaid');
    }

    private function syncFinancials(PreOrder $preOrder, Sale $sale): void
    {
        $preOrder->update([
            'paid_amount' => $sale->paid_amount, 'held_cheque_amount' => $sale->held_cheque_amount,
            'due_amount' => $sale->due_amount, 'payment_status' => $sale->payment_status,
        ]);
    }

    private function activity(PreOrder $preOrder, int $userId, string $action, string $description, ?array $old = null, ?array $new = null): void
    {
        PreOrderActivity::create([
            'pre_order_id' => $preOrder->id, 'user_id' => $userId, 'action' => $action,
            'description' => $description, 'old_values' => $old, 'new_values' => $new,
            'ip_address' => request()?->ip(),
        ]);
    }
}

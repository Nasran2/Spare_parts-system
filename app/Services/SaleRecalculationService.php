<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\TransactionTaxLine;
use Illuminate\Support\Facades\DB;

class SaleRecalculationService
{
    /**
     * Recalculate sale subtotal/tax/discount/total_amount based on returned items,
     * then recompute paid/due/status from payment rows.
     */
    public static function recalculateSaleFinancials(Sale $sale): Sale
    {
        $sale->loadMissing(['items.product', 'payments']);

        $returnedQtyBySaleItemId = self::returnedQtyBySaleItem((int) $sale->id);
        $storedTaxLines = TransactionTaxLine::query()
            ->where('transaction_type', 'sale')
            ->where('transaction_id', $sale->id)
            ->get()
            ->keyBy('transaction_line_id');
        if ($storedTaxLines->isNotEmpty()) {
            return self::recalculateFromTaxSnapshot($sale, $returnedQtyBySaleItemId, $storedTaxLines);
        }

        // Compute original totals from items (sold quantities) and remaining totals after returns.
        // - We treat `sale_items.unit_price` as the effective (after line-discount) unit price.
        // - We use current `products.selling_price` as the original unit price to recover
        //   per-line discounts when only the net unit price is stored in `sale_items`.
        //   (This matches the current POS implementation where line discount reduces effective unit price.)
        $originalNetItemsTotal = 0.0;   // Σ(net unit price * sold qty)
        $originalLineDiscount = 0.0;    // Σ((selling_price - net unit price) * sold qty)
        $originalSubtotal = 0.0;        // Σ(selling_price * sold qty)

        $remainingNetItemsTotal = 0.0; // Σ(net unit price * remaining qty)
        $remainingLineDiscount = 0.0;  // Σ((selling_price - net unit price) * remaining qty)
        $remainingSubtotal = 0.0;      // Σ(selling_price * remaining qty)

        foreach ($sale->items as $item) {
            $soldQty = (int) ($item->quantity ?? 0);
            if ($soldQty <= 0) {
                continue;
            }

            $returnedQty = (int) ($returnedQtyBySaleItemId[$item->id] ?? 0);
            $returnedQty = max(0, min($soldQty, $returnedQty));
            $remainingQty = $soldQty - $returnedQty;

            $netUnitPrice = (float) ($item->unit_price ?? 0);
            $sellingPrice = (float) ($item->product?->selling_price ?? $netUnitPrice);

            $originalNetItemsTotal += $netUnitPrice * $soldQty;
            $originalSubtotal += $sellingPrice * $soldQty;
            $originalLineDiscount += max(0.0, ($sellingPrice - $netUnitPrice) * $soldQty);

            $remainingNetItemsTotal += $netUnitPrice * $remainingQty;
            $remainingSubtotal += $sellingPrice * $remainingQty;
            $remainingLineDiscount += max(0.0, ($sellingPrice - $netUnitPrice) * $remainingQty);
        }

        $originalNetItemsTotal = round($originalNetItemsTotal, 2);
        $originalSubtotal = round($originalSubtotal, 2);
        $originalLineDiscount = round($originalLineDiscount, 2);

        $remainingNetItemsTotal = round($remainingNetItemsTotal, 2);
        $remainingSubtotal = round($remainingSubtotal, 2);
        $remainingLineDiscount = round($remainingLineDiscount, 2);

        $storedDiscount = (float) ($sale->discount ?? 0);
        $storedSubtotal = (float) ($sale->subtotal ?? 0);
        $hasReturns = array_sum($returnedQtyBySaleItemId) > 0;
        $originalDiscount = max($storedDiscount, $originalLineDiscount);

        $netSubtotal = max(0.0, round($remainingSubtotal, 2));

        if ($hasReturns && abs(round($storedSubtotal, 2) - $netSubtotal) <= 0.01) {
            // The sale row is already stored as a net-after-returns invoice.
            // Keep its discount stable; otherwise each list/print recalculation scales it down again.
            $netDiscount = max(0.0, min(round($storedDiscount, 2), $netSubtotal));
        } else {
            // Estimate any cart-level discount that was applied (not embedded into item unit prices).
            // cartDiscount ≈ total_discount - line_discount
            // Use the larger of stored discount and recovered line-discount to avoid the earlier
            // scaling bug forcing discounts down.
            $originalCartDiscount = max(0.0, round($originalDiscount - $originalLineDiscount, 2));

            // Scale cart discount with remaining value (line discounts already handled by remainingLineDiscount).
            $cartScale = $originalNetItemsTotal > 0 ? ($remainingNetItemsTotal / $originalNetItemsTotal) : 0.0;
            $remainingCartDiscount = max(0.0, round($originalCartDiscount * $cartScale, 2));
            $netDiscount = max(0.0, round($remainingLineDiscount + $remainingCartDiscount, 2));
        }

        // Preserve original effective tax rate (if any) on the tax base (subtotal - discount).
        $originalTaxBase = max(0.0, round($originalSubtotal - $originalDiscount, 2));
        $effectiveTaxRate = $originalTaxBase > 0 ? ((float) ($sale->tax ?? 0) / $originalTaxBase) : 0.0;

        $netTaxBase = max(0.0, round($netSubtotal - $netDiscount, 2));
        $netTax = max(0.0, round($netTaxBase * $effectiveTaxRate, 2));

        $netTotal = max(0.0, round($netSubtotal + $netTax - $netDiscount, 2));

        $paymentsSum = (float) $sale->payments->sum(fn ($p) => (float) $p->amount);
        $paid = min($netTotal, max(0.0, round($paymentsSum, 2)));
        $due = max(0.0, round($netTotal - $paid, 2));

        $status = 'unpaid';
        if ($due <= 0.0001 && $netTotal > 0) {
            $status = 'paid';
        } elseif ($paid > 0.0001 && $due > 0.0001) {
            $status = 'partial';
        } elseif ($netTotal <= 0.0001) {
            // Fully returned/cancelled sale => treat as paid (no due)
            $status = 'paid';
            $paid = 0.0;
            $due = 0.0;
        }

        $sale->subtotal = $netSubtotal;
        $sale->discount = $netDiscount;
        $sale->tax = $netTax;
        $sale->total_amount = $netTotal;
        $sale->paid_amount = $paid;
        $sale->due_amount = $due;
        $sale->payment_status = $status;
        $sale->save();

        return $sale;
    }

    private static function recalculateFromTaxSnapshot(Sale $sale, array $returnedQtyByItem, $taxLines): Sale
    {
        $gross = 0;
        $discount = 0;
        $vat = 0;
        $total = 0;
        foreach ($sale->items as $item) {
            $line = $taxLines->get($item->id);
            if (! $line) {
                continue;
            }
            $soldQty = DecimalMath::parse((string) $line->quantity);
            $returnedQty = DecimalMath::parse((string) ($returnedQtyByItem[$item->id] ?? 0));
            $remainingQty = max(0, $soldQty - $returnedQty);
            if ($soldQty <= 0) {
                continue;
            }
            $ratio = fn (string $value) => DecimalMath::roundDiv(
                DecimalMath::parse($value) * $remainingQty,
                $soldQty
            );
            $gross += $ratio($line->gross_amount);
            $discount += $ratio($line->discount_amount);
            $vat += $ratio($line->vat_amount);
            $total += $ratio($line->total_amount);
        }
        $paid = 0;
        foreach ($sale->payments as $payment) {
            $paid += DecimalMath::parse((string) $payment->amount);
        }
        $paid = min($paid, $total);
        $due = max(0, $total - $paid);

        $sale->forceFill([
            'subtotal' => DecimalMath::currency($gross),
            'discount' => DecimalMath::currency($discount),
            'tax' => DecimalMath::currency($vat),
            'total_amount' => DecimalMath::currency($total),
            'paid_amount' => DecimalMath::currency($paid),
            'due_amount' => DecimalMath::currency($due),
            'payment_status' => $due > 0 ? ($paid > 0 ? 'partial' : 'unpaid') : 'paid',
        ])->save();

        return $sale;
    }

    /**
     * Returns a map: sale_item_id => returned_qty (int)
     */
    public static function returnedQtyBySaleItem(int $saleId): array
    {
        return DB::table('sale_return_items')
            ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
            ->where('sale_returns.sale_id', $saleId)
            ->groupBy('sale_return_items.sale_item_id')
            ->pluck(DB::raw('SUM(sale_return_items.quantity) as qty'), 'sale_return_items.sale_item_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }
}

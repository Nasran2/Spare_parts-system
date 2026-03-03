<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

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

        // Estimate any cart-level discount that was applied (not embedded into item unit prices).
        // cartDiscount ≈ total_discount - line_discount
        // Use the larger of stored discount and recovered line-discount to avoid the earlier
        // scaling bug forcing discounts down.
        $storedDiscount = (float) ($sale->discount ?? 0);
        $originalDiscount = max($storedDiscount, $originalLineDiscount);
        $originalCartDiscount = max(0.0, round($originalDiscount - $originalLineDiscount, 2));

        // Scale cart discount with remaining value (line discounts already handled by remainingLineDiscount).
        $cartScale = $originalNetItemsTotal > 0 ? ($remainingNetItemsTotal / $originalNetItemsTotal) : 0.0;
        $remainingCartDiscount = max(0.0, round($originalCartDiscount * $cartScale, 2));

        $netSubtotal = max(0.0, round($remainingSubtotal, 2));
        $netDiscount = max(0.0, round($remainingLineDiscount + $remainingCartDiscount, 2));

        // Preserve original effective tax rate (if any) on the tax base (subtotal - discount).
        $originalTaxBase = max(0.0, round($originalSubtotal - $originalDiscount, 2));
        $effectiveTaxRate = $originalTaxBase > 0 ? ((float) ($sale->tax ?? 0) / $originalTaxBase) : 0.0;

        $netTaxBase = max(0.0, round($netSubtotal - $netDiscount, 2));
        $netTax = max(0.0, round($netTaxBase * $effectiveTaxRate, 2));

        // Also respect VAT toggle if configured to be disabled.
        $vatEnabled = (bool) Setting::get('vat_enabled', false);
        if (!$vatEnabled) {
            $netTax = 0.0;
        }

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

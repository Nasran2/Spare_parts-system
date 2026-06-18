<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockShipment;
use App\Models\StockShipmentAllocation;
use App\Models\StockShipmentItem;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\StoreStockTransfer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryStoreController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderSection($request, 'overview');
    }

    public function stores(Request $request)
    {
        return $this->renderSection($request, 'stores');
    }

    public function shipments(Request $request)
    {
        return $this->renderSection($request, 'shipments');
    }

    public function allocations(Request $request)
    {
        return $this->renderSection($request, 'allocations');
    }

    public function transfers(Request $request)
    {
        return $this->renderSection($request, 'transfers');
    }

    public function report(Request $request)
    {
        return $this->renderSection($request, 'report');
    }

    public function transferHistory(Request $request)
    {
        $query = \App\Models\StoreTransfer::with(['fromStore', 'toStore', 'items.product'])->latest('transfer_date')->latest('id');

        if ($request->filled('from_date')) {
            $query->whereDate('transfer_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('transfer_date', '<=', $request->input('to_date'));
        }
        if ($request->filled('from_store_id')) {
            $query->where('from_store_id', $request->input('from_store_id'));
        }
        if ($request->filled('to_store_id')) {
            $query->where('to_store_id', $request->input('to_store_id'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhere('transfer_no', 'like', "%{$search}%")
                  ->orWhereHas('items.product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                          ->orWhere('sku', 'like', "%{$search}%")
                          ->orWhere('barcode', 'like', "%{$search}%");
                  });
            });
        }

        $transfers = $query->paginate(20)->withQueryString();
        $stores = Store::orderBy('name')->get();

        return view('inventory-stores.transfer-history', compact('transfers', 'stores'));
    }

    private function renderSection(Request $request, string $section)
    {
        $from = $request->input('from') ?: now()->toDateString();
        $to = $request->input('to') ?: now()->toDateString();
        $stores = Store::with(['stocks.product'])->orderByDesc('is_default')->orderBy('name')->get();
        $products = Product::with('storeStocks')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $shipments = StockShipment::with(['supplier', 'items.product', 'items.allocations'])
            ->latest('shipment_date')
            ->take(20)
            ->get();
        if ($section === 'transfers') {
            $transfers = \App\Models\StoreTransfer::with(['fromStore', 'toStore', 'items.product'])
                ->latest('transfer_date')
                ->latest('id')
                ->paginate(15);
        } else {
            $transfers = \App\Models\StoreTransfer::with(['items.product'])
                ->latest('transfer_date')
                ->take(12)
                ->get();
        }

        $overviewStats = $this->overviewStats($from, $to);

        return view('inventory-stores.section', compact('section', 'stores', 'products', 'suppliers', 'shipments', 'transfers', 'overviewStats', 'from', 'to'));
    }

    private function overviewStats(string $from, string $to): array
    {
        $shipmentItems = StockShipmentItem::query()
            ->join('stock_shipments', 'stock_shipment_items.stock_shipment_id', '=', 'stock_shipments.id')
            ->whereDate('stock_shipments.shipment_date', '>=', $from)
            ->whereDate('stock_shipments.shipment_date', '<=', $to);

        $periodShipments = StockShipment::query()
            ->whereDate('shipment_date', '>=', $from)
            ->whereDate('shipment_date', '<=', $to);

        $periodTransfers = StoreStockTransfer::query()
            ->whereDate('transfer_date', '>=', $from)
            ->whereDate('transfer_date', '<=', $to);

        $periodAllocations = StockShipmentAllocation::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        return [
            'stores' => Store::where('is_active', true)->count(),
            'stock_qty' => (float) StoreStock::sum('quantity'),
            'shipments' => (clone $periodShipments)->count(),
            'shipment_value' => (float) (clone $shipmentItems)->selectRaw('SUM(stock_shipment_items.quantity * stock_shipment_items.landed_unit_cost) as total')->value('total'),
            'allocated_qty' => (float) (clone $periodAllocations)->sum('quantity'),
            'transfers' => (clone $periodTransfers)->count(),
            'transfer_qty' => (float) (clone $periodTransfers)->sum('quantity'),
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:stores,code',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
        ]);

        $store = DB::transaction(function () use ($validated) {
            if (! empty($validated['is_default'])) {
                Store::query()->update(['is_default' => false]);
            }
            return Store::create($validated + ['is_default' => false]);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Store created successfully',
                'store' => $store
            ]);
        }

        return back()->with('success', 'Store created.');
    }

    public function setAsDefault(\App\Models\Store $store)
    {
        DB::transaction(function () use ($store) {
            \App\Models\Store::query()->update(['is_default' => false]);
            $store->update(['is_default' => true]);
        });
        return back()->with('success', 'Default store updated.');
    }

    public function shipment(Request $request)
    {
        $validated = $request->validate([
            'shipment_no' => 'required|string|max:100|unique:stock_shipments,shipment_no',
            'grn_no' => 'required|string|max:100|unique:stock_shipments,grn_no',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'shipment_date' => 'required|date',
            'received_date' => 'nullable|date',
            'freight_cost' => 'nullable|numeric|min:0',
            'duty_cost' => 'nullable|numeric|min:0',
            'other_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $shipment = StockShipment::create($validated + ['status' => 'received']);
            $baseValue = collect($validated['items'])->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_cost']);
            $extraCosts = (float) ($validated['freight_cost'] ?? 0) + (float) ($validated['duty_cost'] ?? 0) + (float) ($validated['other_cost'] ?? 0);

            foreach ($validated['items'] as $item) {
                $lineValue = (float) $item['quantity'] * (float) $item['unit_cost'];
                $costShare = $baseValue > 0 ? ($lineValue / $baseValue) * $extraCosts : 0;
                $landedUnitCost = (float) $item['unit_cost'] + ($costShare / max((float) $item['quantity'], 0.001));

                StockShipmentItem::create([
                    'stock_shipment_id' => $shipment->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'landed_unit_cost' => round($landedUnitCost, 2),
                    'selling_price' => $item['selling_price'] ?? 0,
                ]);
            }
        });

        return back()->with('success', 'Shipment GRN recorded.');
    }

    public function allocate(Request $request)
    {
        $validated = $request->validate([
            'stock_shipment_item_id' => 'required|exists:stock_shipment_items,id',
            'store_id' => 'required|exists:stores,id',
            'quantity' => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($validated) {
            $item = StockShipmentItem::with('shipment')->lockForUpdate()->findOrFail($validated['stock_shipment_item_id']);
            $allocatedQty = StockShipmentAllocation::where('stock_shipment_item_id', $item->id)->sum('quantity');
            if (((float) $allocatedQty + (float) $validated['quantity']) > (float) $item->quantity) {
                abort(422, 'Allocation is more than available shipment quantity.');
            }

            StockShipmentAllocation::create($validated);
            $this->addStoreStock((int) $validated['store_id'], (int) $item->product_id, (float) $validated['quantity']);

            $item->shipment->update([
                'status' => ((float) $allocatedQty + (float) $validated['quantity']) >= (float) $item->quantity ? 'allocated' : 'received',
            ]);
        });

        return back()->with('success', 'Shipment stock allocated to store.');
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'from_store_id' => 'required|different:to_store_id|exists:stores,id',
            'to_store_id' => 'required|exists:stores,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'transfer_date' => 'required|date',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
            'shipping_cost' => 'nullable|numeric|min:0',
            'additional_expense' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $items = collect($validated['items'])
                ->groupBy('product_id')
                ->map(fn ($rows, $productId) => [
                    'product_id' => (int) $productId,
                    'quantity' => $rows->sum(fn ($row) => (float) $row['quantity']),
                ])
                ->values();

            foreach ($items as $item) {
                $totalFromStock = StoreStock::where('store_id', $validated['from_store_id'])
                    ->where('product_id', $item['product_id'])
                    ->sum('quantity');

                if ($totalFromStock < (float) $item['quantity']) {
                    $product = Product::find($item['product_id']);
                    abort(422, 'Source store does not have enough stock for '.($product?->name ?? 'selected product').'.');
                }
            }

            $transferGroup = \App\Models\StoreTransfer::create([
                'from_store_id' => $validated['from_store_id'],
                'to_store_id' => $validated['to_store_id'],
                'transfer_date' => $validated['transfer_date'],
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'additional_expense' => $validated['additional_expense'] ?? 0,
            ]);

            foreach ($items as $index => $item) {
                $this->deductStoreStock($validated['from_store_id'], (int) $item['product_id'], (float) $item['quantity']);
                $this->addStoreStock((int) $validated['to_store_id'], (int) $item['product_id'], (float) $item['quantity']);

                StoreStockTransfer::create([
                    'store_transfer_id' => $transferGroup->id,
                    'from_store_id' => $validated['from_store_id'],
                    'to_store_id' => $validated['to_store_id'],
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'transfer_date' => $validated['transfer_date'],
                    'reference_no' => $validated['reference_no'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'shipping_cost' => $index === 0 ? (float) ($validated['shipping_cost'] ?? 0) : 0,
                    'additional_expense' => $index === 0 ? (float) ($validated['additional_expense'] ?? 0) : 0,
                ]);
            }
        });

        return back()->with('success', 'Store transfer completed.');
    }

    private function addStoreStock(int $storeId, int $productId, float $quantity): void
    {
        $stock = StoreStock::firstOrCreate(
            ['store_id' => $storeId, 'product_id' => $productId, 'product_price_id' => null],
            ['quantity' => 0]
        );
        $stock->increment('quantity', $quantity);
    }

    private function deductStoreStock(int $storeId, int $productId, float $quantity): void
    {
        $stocks = StoreStock::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->get();

        $remainingToDeduct = $quantity;

        foreach ($stocks as $stock) {
            if ($remainingToDeduct <= 0) break;

            $deductAmount = min($stock->quantity, $remainingToDeduct);
            $stock->decrement('quantity', $deductAmount);
            $remainingToDeduct -= $deductAmount;
        }

        // If there's still remaining (which shouldn't happen if validation passed), deduct from the first available or create one.
        if ($remainingToDeduct > 0) {
            $stock = StoreStock::firstOrCreate(
                ['store_id' => $storeId, 'product_id' => $productId, 'product_price_id' => null],
                ['quantity' => 0]
            );
            $stock->decrement('quantity', $remainingToDeduct);
        }
    }

    public function updateTransfer(Request $request, $id)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'transfer_date' => 'required|date',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
            'shipping_cost' => 'nullable|numeric|min:0',
            'additional_expense' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $id) {
            $transferGroup = \App\Models\StoreTransfer::with('items')->lockForUpdate()->findOrFail($id);

            // Revert all original stock
            foreach ($transferGroup->items as $transfer) {
                $toStock = StoreStock::where('store_id', $transfer->to_store_id)
                    ->where('product_id', $transfer->product_id)
                    ->lockForUpdate()
                    ->first();
                    
                if ($toStock) {
                    $toStock->decrement('quantity', $transfer->quantity);
                }
                $this->addStoreStock($transfer->from_store_id, $transfer->product_id, $transfer->quantity);
            }

            // Group and sum new items
            $newItems = collect($validated['items'])
                ->groupBy('product_id')
                ->map(fn ($rows, $productId) => [
                    'product_id' => (int) $productId,
                    'quantity' => $rows->sum(fn ($row) => (float) $row['quantity']),
                ])
                ->values();

            // Check if new quantities are available
            foreach ($newItems as $item) {
                $totalFromStock = StoreStock::where('store_id', $transferGroup->from_store_id)
                    ->where('product_id', $item['product_id'])
                    ->sum('quantity');

                if ($totalFromStock < (float) $item['quantity']) {
                    $product = Product::find($item['product_id']);
                    abort(422, 'Source store does not have enough stock for '.($product?->name ?? 'selected product').'.');
                }
            }

            // Delete old items
            $transferGroup->items()->delete();

            // Update parent group
            $transferGroup->update([
                'transfer_date' => $validated['transfer_date'],
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'additional_expense' => $validated['additional_expense'] ?? 0,
            ]);

            // Apply new stock and create new items
            foreach ($newItems as $index => $item) {
                $this->deductStoreStock($transferGroup->from_store_id, (int) $item['product_id'], (float) $item['quantity']);
                $this->addStoreStock($transferGroup->to_store_id, (int) $item['product_id'], (float) $item['quantity']);

                StoreStockTransfer::create([
                    'store_transfer_id' => $transferGroup->id,
                    'from_store_id' => $transferGroup->from_store_id,
                    'to_store_id' => $transferGroup->to_store_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'transfer_date' => $validated['transfer_date'],
                    'reference_no' => $validated['reference_no'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'shipping_cost' => $index === 0 ? (float) ($validated['shipping_cost'] ?? 0) : 0,
                    'additional_expense' => $index === 0 ? (float) ($validated['additional_expense'] ?? 0) : 0,
                ]);
            }
        });

        return back()->with('success', 'Store transfer updated.');
    }

    public function destroyTransfer($id)
    {
        DB::transaction(function () use ($id) {
            $transferGroup = \App\Models\StoreTransfer::with('items')->lockForUpdate()->findOrFail($id);

            foreach ($transferGroup->items as $transfer) {
                // Revert stock
                $toStock = StoreStock::where('store_id', $transfer->to_store_id)
                    ->where('product_id', $transfer->product_id)
                    ->lockForUpdate()
                    ->first();
                    
                if ($toStock) {
                    $toStock->decrement('quantity', $transfer->quantity);
                }
                
                $this->addStoreStock($transfer->from_store_id, $transfer->product_id, $transfer->quantity);
            }

            $transferGroup->delete(); // This cascades and deletes the items
        });

        return back()->with('success', 'Store transfer deleted and stock reverted.');
    }
}

<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::transaction(function() {
    $transferGroup = \App\Models\StoreTransfer::with('items')->lockForUpdate()->findOrFail(2);
    foreach ($transferGroup->items as $transfer) {
        $toStock = \App\Models\StoreStock::where('store_id', $transfer->to_store_id)
            ->where('product_id', $transfer->product_id)
            ->lockForUpdate()
            ->first();
            
        if ($toStock) {
            $toStock->decrement('quantity', $transfer->quantity);
        }
        
        $stock = \App\Models\StoreStock::firstOrCreate(
            ['store_id' => $transfer->from_store_id, 'product_id' => $transfer->product_id, 'product_price_id' => null],
            ['quantity' => 0]
        );
        $stock->increment('quantity', $transfer->quantity);
    }
    
    $newItems = $transferGroup->items->map(function($item) {
        return ['product_id' => $item->product_id, 'quantity' => $item->quantity];
    });
    
    foreach ($newItems as $item) {
        $fromStock = \App\Models\StoreStock::where('store_id', $transferGroup->from_store_id)
            ->where('product_id', $item['product_id'])
            ->lockForUpdate()
            ->first();

        echo "Product ID: {$item['product_id']}\n";
        echo "Requested Qty: {$item['quantity']}\n";
        echo "From Stock ID: " . ($fromStock ? $fromStock->id : 'null') . "\n";
        echo "From Stock Qty: " . ($fromStock ? $fromStock->quantity : 'null') . "\n";
        echo "Comparison: " . ((float)$fromStock->quantity < (float)$item['quantity'] ? 'TRUE (Aborts)' : 'FALSE (Passes)') . "\n\n";
    }
    
    throw new Exception("Rollback");
});

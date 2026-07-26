<?php
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\StoreStock;
use App\Models\Store;
use App\Models\Unit;

$parts = [
    ['name' => 'Brake Pad Set - Front', 'sku' => 'BPF-001', 'cost' => 1500, 'price' => 2200],
    ['name' => 'Brake Pad Set - Rear', 'sku' => 'BPR-002', 'cost' => 1200, 'price' => 1800],
    ['name' => 'Oil Filter - Standard', 'sku' => 'OF-100', 'cost' => 450, 'price' => 850],
    ['name' => 'Air Filter - High Flow', 'sku' => 'AF-200', 'cost' => 900, 'price' => 1500],
    ['name' => 'Spark Plug - Iridium', 'sku' => 'SP-IR-4', 'cost' => 2500, 'price' => 3800],
    ['name' => 'Timing Belt Kit', 'sku' => 'TBK-050', 'cost' => 8500, 'price' => 12500],
    ['name' => 'Water Pump', 'sku' => 'WP-300', 'cost' => 4200, 'price' => 6500],
    ['name' => 'Radiator Coolant (5L)', 'sku' => 'RC-5L', 'cost' => 1800, 'price' => 2600],
    ['name' => 'Synthetic Motor Oil 10W-40 (4L)', 'sku' => 'MO-10W40-4L', 'cost' => 3500, 'price' => 5200],
    ['name' => 'Wiper Blade 22"', 'sku' => 'WB-22', 'cost' => 600, 'price' => 1100],
    ['name' => 'Wiper Blade 18"', 'sku' => 'WB-18', 'cost' => 550, 'price' => 1000],
    ['name' => 'Headlight Bulb H4', 'sku' => 'HB-H4', 'cost' => 800, 'price' => 1400],
    ['name' => 'Battery 12V 65Ah', 'sku' => 'BAT-65AH', 'cost' => 12000, 'price' => 16500],
    ['name' => 'Alternator Assembly', 'sku' => 'ALT-100', 'cost' => 18000, 'price' => 24000],
    ['name' => 'Starter Motor', 'sku' => 'STM-200', 'cost' => 14500, 'price' => 19500],
    ['name' => 'Shock Absorber - Front', 'sku' => 'SAF-01', 'cost' => 6500, 'price' => 9500],
    ['name' => 'Shock Absorber - Rear', 'sku' => 'SAR-02', 'cost' => 6000, 'price' => 8800],
    ['name' => 'Fuel Filter', 'sku' => 'FF-500', 'cost' => 1100, 'price' => 1800],
    ['name' => 'Cabin Air Filter', 'sku' => 'CAF-600', 'cost' => 750, 'price' => 1350],
    ['name' => 'CV Joint Kit', 'sku' => 'CVJ-800', 'cost' => 4800, 'price' => 7200],
    ['name' => 'Clutch Plate Kit', 'sku' => 'CPK-900', 'cost' => 12500, 'price' => 18000],
    ['name' => 'Engine Mount', 'sku' => 'EM-010', 'cost' => 3200, 'price' => 5000],
];

$unit = Unit::first();
$unit_id = $unit ? $unit->id : null;
$stores = Store::all();

foreach ($parts as $part) {
    $product = Product::create([
        'name' => $part['name'],
        'sku' => $part['sku'],
        'unit_id' => $unit_id,
        'cost_price' => $part['cost'],
        'selling_price' => $part['price'],
        'stock_quantity' => rand(10, 100),
        'status' => 'active',
        'is_stock_tracked' => true,
        'sale_price_mode' => 'global',
        'purchase_price_mode' => 'global',
    ]);
    
    // Create Default Price
    ProductPrice::create([
        'product_id' => $product->id,
        'cost_price' => $part['cost'],
        'selling_price' => $part['price'],
        'stock_qty' => $product->stock_quantity,
        'is_default' => true,
        'status' => 'active',
    ]);
    
    // Distribute stock among stores
    if ($stores->count() > 0) {
        $remaining = $product->stock_quantity;
        foreach ($stores as $index => $store) {
            $qty = ($index === $stores->count() - 1) ? $remaining : floor($remaining / 2);
            StoreStock::create([
                'store_id' => $store->id,
                'product_id' => $product->id,
                'quantity' => $qty,
            ]);
            $remaining -= $qty;
        }
    }
    
    echo "Created: " . $part['name'] . "\n";
}
echo "Done seeding products.\n";

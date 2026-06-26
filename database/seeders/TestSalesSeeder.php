<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestSalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = \Faker\Factory::create();
        $store = \App\Models\Store::first();
        $user = \App\Models\User::first();

        // Ensure we have some products
        if (\App\Models\Product::count() === 0) {
            $unit = \App\Models\Unit::firstOrCreate(['name' => 'Pieces', 'short_name' => 'pcs']);
            for ($i = 1; $i <= 10; $i++) {
                $cost = random_int(100, 500);
                $sell = $cost + random_int(50, 200);
                \App\Models\Product::create([
                    'name' => 'Demo Product ' . $i,
                    'sku' => 'DEMO-SKU-' . $i,
                    'barcode' => 'DEMO-BAR-' . $i,
                    'cost_price' => $cost,
                    'selling_price' => $sell,
                    'unit_id' => $unit->id,
                    'stock_quantity' => 1000,
                    'is_active' => true,
                ]);
            }
        }

        $products = \App\Models\Product::all();

        for ($i = 0; $i < 120; $i++) {
            $date = now()->subDays(random_int(0, 5));
            $subtotal = 0;

            $sale = \App\Models\Sale::create([
                'store_id' => $store->id ?? 1,
                'user_id' => $user->id ?? 1,
                'sale_no' => 'TEMP-' . uniqid(),
                'sale_date' => $date,
                'subtotal' => 0,
                'tax' => 0,
                'discount' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'sale_type' => 'sale',
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $numItems = random_int(1, 5);
            $selectedProducts = $products->random($numItems);

            foreach ($selectedProducts as $product) {
                $qty = random_int(1, 3);
                $price = $product->selling_price;
                $lineTotal = $qty * $price;
                $subtotal += $lineTotal;

                \App\Models\SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total' => $lineTotal,
                ]);
            }

            $sale->update([
                'sale_no' => \App\Models\Sale::generateNumber('sale', $date),
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'paid_amount' => $subtotal,
            ]);
        }
    }
}

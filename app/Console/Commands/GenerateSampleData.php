<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateSampleData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-sample-data {--clean : Remove existing sample data first}';
    protected $description = 'Generate realistic sample products and sales for testing the system';

    public function handle()
    {
        $this->info('Generating Sample Data...');
        
        $categories = ['Brake Pads', 'Oil Filters', 'Spark Plugs', 'Engine Oil', 'Wiper Blades', 'Battery', 'Headlights'];
        $brands = ['Bosch', 'NGK', 'Mobil 1', 'Castrol', 'Michelin', 'Brembo', 'Denso'];
        
        $catIds = [];
        foreach($categories as $cat) {
            $catIds[] = \App\Models\Category::firstOrCreate(['name' => $cat], ['slug' => \Illuminate\Support\Str::slug($cat)])->id;
        }
        
        $brandIds = [];
        foreach($brands as $brand) {
            $brandIds[] = \App\Models\Brand::firstOrCreate(['name' => $brand], ['slug' => \Illuminate\Support\Str::slug($brand)])->id;
        }
        
        $unitId = \App\Models\Unit::firstOrCreate(['name' => 'Pieces'], ['short_name' => 'pcs'])->id;
        
        $products = [];
        for($i=1; $i<=50; $i++) {
            $cost = rand(10, 100);
            $products[] = \App\Models\Product::create([
                'name' => 'Sample Part ' . \Illuminate\Support\Str::random(5),
                'sku' => 'SMP-' . str_pad($i, 4, '0', STR_PAD_LEFT) . \Illuminate\Support\Str::random(3),
                'category_id' => $catIds[array_rand($catIds)],
                'brand_id' => $brandIds[array_rand($brandIds)],
                'purchase_price' => $cost,
                'selling_price' => $cost * 1.5,
                'tax_type' => 'exclusive',
                'tax_rate' => 0,
                'current_stock' => rand(10, 100),
                'alert_quantity' => 5,
                'barcode_type' => 'CODE128',
                'unit_id' => $unitId,
                'is_active' => true,
            ]);
        }
        $this->info('Created 50 products.');
        
        $customers = [];
        for($i=1; $i<=5; $i++) {
            $customers[] = \App\Models\Customer::create([
                'name' => 'Sample Customer ' . $i,
                'phone' => '07' . rand(10000000, 99999999),
                'address' => 'Sample Address ' . $i,
                'tin' => 'TIN' . rand(100000000, 999999999),
                'is_active' => true,
            ]);
        }
        $this->info('Created 5 customers.');
        
        $user = \App\Models\User::first();
        if(!$user) {
            $this->error('No users found to assign sales to.');
            return;
        }

        for($i=1; $i<=20; $i++) {
            $customer = rand(0, 1) ? $customers[array_rand($customers)] : null;
            $itemsCount = rand(1, 5);
            $subtotal = 0;
            
            $saleItems = [];
            for($j=0; $j<$itemsCount; $j++) {
                $product = $products[array_rand($products)];
                $qty = rand(1, 3);
                $total = $qty * $product->selling_price;
                $subtotal += $total;
                $saleItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $product->selling_price,
                    'total' => $total, // wait, it's 'total' not 'subtotal' in SaleItem
                ];
            }
            
            $totalAmount = $subtotal;
            $paymentMethod = rand(0, 1) ? 'cash' : 'card';
            
            $sale = \App\Models\Sale::create([
                'customer_id' => $customer?->id,
                'user_id' => $user->id,
                'subtotal' => $subtotal,
                'tax' => 0,
                'discount' => 0,
                'total_amount' => $totalAmount,
                'paid_amount' => $totalAmount,
                'due_amount' => 0,
                'payment_status' => 'paid',
                'payment_method' => $paymentMethod,
                'sale_date' => now()->subDays(rand(0, 30)),
                'sale_type' => 'sale',
            ]);
            
            foreach($saleItems as $item) {
                $sale->items()->create($item);
            }
            
            $payment = \App\Models\Payment::create([
                'sale_id' => $sale->id,
                'customer_id' => $customer?->id,
                'amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'payment_date' => $sale->sale_date->toDateString(),
            ]);
            
            try {
                app(\App\Services\Accounting\SalePaymentAccountingService::class)->recordSalePayment($payment, $sale, $user->id);
            } catch(\Exception $e) {
                // Ignore accounting errors for sample data
            }
        }
        $this->info('Created 20 sales.');
        
        $this->info('Sample data generated successfully!');
    }
}

<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SystemNotification;
use App\Models\User;

class StockAlertService
{
    public static function check(Product $product): void
    {
        if ($product->stock_quantity <= $product->alert_quantity) {
            $title = 'Low Stock: ' . $product->name;
            $message = 'Stock level (' . $product->stock_quantity . ') reached alert threshold (' . $product->alert_quantity . ').';
            $data = [
                'product_id' => $product->id,
                'stock_quantity' => $product->stock_quantity,
                'alert_quantity' => $product->alert_quantity,
            ];

            // Notify all admin users (role name 'admin') or fallback to first user.
            $users = User::whereHas('role', fn($q) => $q->where('name', 'admin'))->get();
            if ($users->isEmpty()) {
                $fallback = User::first();
                if ($fallback) {
                    $users = collect([$fallback]);
                }
            }

            foreach ($users as $user) {
                SystemNotification::create([
                    'user_id' => $user->id,
                    'type' => 'stock_alert',
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                ]);
            }
        }
    }
}

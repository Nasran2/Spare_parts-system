<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'reference_no')) {
                $table->string('reference_no', 191)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('payments', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::create('pre_orders', function (Blueprint $table) {
            $table->id();
            $table->string('pre_order_number', 30)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->unique()->constrained('sales')->nullOnDelete();
            $table->date('pre_order_date');
            $table->string('document_type', 30)->default('quotation');
            $table->string('vehicle_name');
            $table->string('registration_number')->nullable();
            $table->string('chassis_number')->nullable();
            $table->text('vehicle_description')->nullable();
            $table->string('vehicle_image')->nullable();
            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->string('bill_discount_type', 20)->default('fixed');
            $table->decimal('bill_discount_value', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('rounding_adjustment', 20, 4)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('held_cheque_amount', 15, 2)->default(0);
            $table->decimal('due_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'pre_order_date']);
            $table->index(['payment_status', 'pre_order_date']);
            $table->index('expected_delivery_date');
        });

        Schema::create('pre_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_order_id')->constrained('pre_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_price_id')->nullable()->constrained('product_prices')->nullOnDelete();
            $table->string('original_product_name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('quoted_price', 15, 2);
            $table->decimal('final_price', 15, 2);
            $table->string('discount_type', 20)->default('fixed');
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->string('sync_status', 20)->default('unlinked');
            $table->json('tax_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['pre_order_id', 'sync_status']);
            $table->index('original_product_name');
        });

        Schema::create('pre_order_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_order_id')->constrained('pre_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->text('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['pre_order_id', 'created_at']);
        });

        $permissions = [
            'preorder_view', 'preorder_create', 'preorder_edit', 'preorder_cancel',
            'preorder_complete', 'preorder_reopen', 'preorder_payment_view',
            'preorder_payment_create', 'preorder_payment_edit', 'preorder_sync_product',
            'preorder_change_price', 'preorder_print_quotation', 'preorder_print_invoice',
            'preorder_view_cost', 'preorder_view_profit', 'preorder_reports',
        ];

        foreach (DB::table('roles')->get(['id', 'name', 'permissions']) as $role) {
            $name = strtolower(trim((string) $role->name));
            $existing = json_decode($role->permissions ?: '[]', true) ?: [];
            if (in_array($name, ['admin', 'super admin', 'superadmin', 'super_admin'], true)) {
                $grant = $permissions;
            } elseif ($name === 'manager') {
                $grant = $permissions;
            } elseif ($name === 'cashier') {
                $grant = [
                    'preorder_view', 'preorder_create', 'preorder_edit',
                    'preorder_payment_view', 'preorder_payment_create',
                    'preorder_print_quotation', 'preorder_print_invoice',
                ];
            } else {
                $grant = [];
            }
            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_unique(array_merge($existing, $grant)))),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_order_activities');
        Schema::dropIfExists('pre_order_items');
        Schema::dropIfExists('pre_orders');

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
            if (Schema::hasColumn('payments', 'reference_no')) {
                $table->dropColumn('reference_no');
            }
        });
    }
};

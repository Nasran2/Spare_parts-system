<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->decimal('rate', 9, 4)->default(0);
            $table->boolean('is_enabled')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_enabled', 'effective_from']);
        });

        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->boolean('vat_enabled')->default(false);
            $table->boolean('vat_registered')->default(false);
            $table->string('supplier_tin', 30)->nullable();
            $table->decimal('default_vat_rate', 9, 4)->default(18);
            $table->string('default_sale_price_mode', 20)->default('inclusive');
            $table->string('default_purchase_price_mode', 20)->default('inclusive');
            $table->string('customer_invoice_vat_display', 30)->default('hide_inclusive');
            $table->boolean('regular_invoice_vat_note')->default(false);
            $table->boolean('allow_product_override')->default(false);
            $table->string('invoice_prefix', 30)->default('TAX');
            $table->unsignedBigInteger('starting_invoice_number')->default(1);
            $table->unsignedBigInteger('next_invoice_number')->default(1);
            $table->string('branch_code', 20)->nullable();
            $table->string('active_template_version', 40)->default('sl-vat-2025.1');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['effective_from', 'effective_to']);
        });

        Schema::create('product_tax_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->foreignId('tax_type_id')->nullable()->constrained('tax_types')->nullOnDelete();
            $table->string('tax_status', 30)->default('standard');
            $table->decimal('vat_rate', 9, 4)->nullable();
            $table->string('sale_price_mode', 20)->default('global');
            $table->string('purchase_price_mode', 20)->default('global');
            $table->boolean('output_vat_allowed')->default(true);
            $table->boolean('input_vat_allowed')->default(true);
            $table->timestamps();
            $table->index(['tax_status', 'vat_rate']);
        });

        Schema::create('transaction_tax_lines', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type', 30);
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('transaction_line_id')->nullable();
            $table->string('tax_type', 30)->default('VAT');
            $table->string('tax_status', 30)->default('standard');
            $table->decimal('tax_rate', 9, 4)->default(0);
            $table->string('price_mode', 20)->default('inclusive');
            $table->decimal('original_unit_price', 20, 4)->default(0);
            $table->decimal('quantity', 20, 4)->default(0);
            $table->decimal('gross_amount', 20, 4)->default(0);
            $table->decimal('discount_amount', 20, 4)->default(0);
            $table->decimal('taxable_amount', 20, 4)->default(0);
            $table->decimal('vat_amount', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->decimal('rounding_adjustment', 20, 4)->default(0);
            $table->boolean('input_vat_claimable')->default(false);
            $table->string('tax_period', 7);
            $table->json('setting_snapshot');
            $table->timestamps();
            $table->unique(
                ['transaction_type', 'transaction_id', 'transaction_line_id', 'tax_type'],
                'transaction_tax_line_unique'
            );
            $table->index(['tax_period', 'transaction_type']);
            $table->index(['transaction_id', 'transaction_line_id']);
        });

        Schema::create('tax_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('tax_period', 7);
            $table->string('transaction_type', 30);
            $table->unsignedBigInteger('transaction_id');
            $table->string('invoice_number')->nullable();
            $table->string('direction', 20);
            $table->decimal('taxable_amount', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('adjustment_amount', 20, 4)->default(0);
            $table->string('status', 20)->default('posted');
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['transaction_type', 'transaction_id', 'direction'],
                'tax_ledger_transaction_unique'
            );
            $table->index(['tax_period', 'direction', 'status']);
            $table->index('invoice_number');
        });

        Schema::create('tax_payments', function (Blueprint $table) {
            $table->id();
            $table->string('tax_period', 7);
            $table->date('payment_date');
            $table->string('reference')->unique();
            $table->string('payment_method', 30);
            $table->foreignId('account_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->decimal('output_vat', 20, 4)->default(0);
            $table->decimal('input_vat', 20, 4)->default(0);
            $table->decimal('adjustments', 20, 4)->default(0);
            $table->decimal('previous_balance', 20, 4)->default(0);
            $table->decimal('payable_amount', 20, 4)->default(0);
            $table->decimal('paid_amount', 20, 4)->default(0);
            $table->decimal('remaining_amount', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tax_period', 'status']);
        });

        Schema::create('tax_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('tax_period', 7);
            $table->date('adjustment_date');
            $table->string('adjustment_type', 20);
            $table->decimal('amount', 20, 4);
            $table->text('reason');
            $table->string('reference')->unique();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tax_period', 'status', 'adjustment_type']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('tax_invoice_number', 80)->nullable()->unique()->after('sale_no');
            $table->string('tax_template_version', 40)->nullable()->after('tax_invoice_number');
            $table->decimal('rounding_adjustment', 20, 4)->default(0)->after('tax');
            $table->json('tax_snapshot')->nullable()->after('rounding_adjustment');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('supplier_tax_invoice_number', 100)->nullable()->after('reference_no');
            $table->date('supplier_tax_invoice_date')->nullable()->after('supplier_tax_invoice_number');
            $table->string('purchase_vat_mode', 20)->nullable()->after('supplier_tax_invoice_date');
            $table->decimal('taxable_purchase_value', 20, 4)->default(0)->after('tax_amount');
            $table->boolean('input_vat_claimable')->default(false)->after('taxable_purchase_value');
            $table->string('tax_period', 7)->nullable()->after('input_vat_claimable');
            $table->json('tax_snapshot')->nullable()->after('tax_period');
            $table->unique(
                ['supplier_id', 'supplier_tax_invoice_number'],
                'purchases_supplier_tax_invoice_unique'
            );
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('tin', 30)->nullable()->unique()->after('name');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('tin', 30)->nullable()->unique()->after('name');
        });

        DB::table('tax_types')->insert([
            'name' => 'Value Added Tax',
            'code' => 'VAT',
            'rate' => '18.0000',
            'is_enabled' => true,
            'effective_from' => '2025-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tax_settings')->insert([
            'version' => 1,
            'vat_enabled' => false,
            'vat_registered' => false,
            'default_vat_rate' => '18.0000',
            'default_sale_price_mode' => 'inclusive',
            'default_purchase_price_mode' => 'inclusive',
            'customer_invoice_vat_display' => 'hide_inclusive',
            'regular_invoice_vat_note' => false,
            'allow_product_override' => false,
            'invoice_prefix' => 'TAX',
            'starting_invoice_number' => 1,
            'next_invoice_number' => 1,
            'active_template_version' => 'sl-vat-2025.1',
            'effective_from' => '2025-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissions = [
            'tax.view',
            'tax.settings.manage',
            'tax.reports.view',
            'tax.reports.export',
            'tax.payment.create',
            'tax.payment.edit',
            'tax.payment.delete',
            'tax.adjustment.manage',
            'tax.invoice.print',
        ];

        DB::table('roles')->get()->each(function ($role) use ($permissions) {
            $current = json_decode($role->permissions ?: '[]', true) ?: [];
            $roleName = strtolower(trim((string) $role->name));
            if (in_array($roleName, ['admin', 'super admin', 'superadmin', 'super_admin'], true)) {
                DB::table('roles')->where('id', $role->id)->update([
                    'permissions' => json_encode(array_values(array_unique(array_merge($current, $permissions)))),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn('tin'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('tin'));

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique('purchases_supplier_tax_invoice_unique');
            $table->dropColumn([
                'supplier_tax_invoice_number',
                'supplier_tax_invoice_date',
                'purchase_vat_mode',
                'taxable_purchase_value',
                'input_vat_claimable',
                'tax_period',
                'tax_snapshot',
            ]);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['tax_invoice_number']);
            $table->dropColumn([
                'tax_invoice_number',
                'tax_template_version',
                'rounding_adjustment',
                'tax_snapshot',
            ]);
        });

        Schema::dropIfExists('tax_adjustments');
        Schema::dropIfExists('tax_payments');
        Schema::dropIfExists('tax_ledger_entries');
        Schema::dropIfExists('transaction_tax_lines');
        Schema::dropIfExists('product_tax_settings');
        Schema::dropIfExists('tax_settings');
        Schema::dropIfExists('tax_types');
    }
};

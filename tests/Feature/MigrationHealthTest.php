<?php

namespace Tests\Feature;

use App\Services\MigrationHealthService;
use App\Support\Migrations\Schema as MigrationSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $role = \App\Models\Role::create(['name' => 'Super Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(\App\Models\User::factory()->create(['role_id' => $role->id, 'is_active' => true]));
    }


    public function test_foreign_key_deferral_is_scoped_and_covers_create_and_alter(): void
    {
        try {
            MigrationSchema::deferForeignKeys(function () {
                MigrationSchema::create('migration_child_test', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('store_id')->nullable()->constrained('stores');
                });
                MigrationSchema::table('migration_child_test', fn (Blueprint $table) => $table->foreign('store_id')->references('id')->on('stores'));
                $this->assertSame([], Schema::getForeignKeys('migration_child_test'));
                throw new \RuntimeException('Interrupted upgrade');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Interrupted upgrade', $exception->getMessage());
        }
        $this->assertFalse(MigrationSchema::foreignKeysDeferred());
        MigrationSchema::table('migration_child_test', fn (Blueprint $table) => $table->foreign('store_id')->references('id')->on('stores'));
        $this->assertCount(1, Schema::getForeignKeys('migration_child_test'));
    }

    public function test_failure_page_keeps_completed_repairs_and_migration_output(): void
    {
        $this->app->instance(MigrationHealthService::class, new class extends MigrationHealthService
        {
            public function migrate(): array
            {
                throw new \RuntimeException('A later step failed');
            }

            public function progress(): array
            {
                return ['repairs' => ['Created missing parent table: store_transfers'], 'output' => 'Earlier migrations completed'];
            }
        });
        $this->post('/migration')->assertStatus(500)
            ->assertSee('Maintenance failed. Review the server logs.')->assertDontSee('A later step failed')
            ->assertSee('Created missing parent table: store_transfers')
            ->assertSee('Earlier migrations completed')
            ->assertSee('2026-10-18.3')
            ->assertDontSee('No preflight repair was needed.');
    }

    public function test_read_only_verification_checks_real_schema_and_does_not_repair_it(): void
    {
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('phone'));
        $before = DB::table('migrations')->count();

        $this->get('/migration?check=1')->assertStatus(500)
            ->assertSee('Missing column: stores.phone');

        $this->assertFalse(Schema::hasColumn('stores', 'phone'));
        $this->assertSame($before, DB::table('migrations')->count());
    }

    public function test_missing_release_file_blocks_migration_before_database_changes(): void
    {
        $service = new class extends MigrationHealthService
        {
            public function contract(): array
            {
                $contract = parent::contract();
                $contract['migrations'][] = '2026_01_01_000000_missing_upload';

                return $contract;
            }
        };
        $this->app->instance(MigrationHealthService::class, $service);
        $before = DB::table('migrations')->count();
        $this->post('/migration')->assertStatus(500)
            ->assertSee('Maintenance failed. Review the server logs.');
        $this->assertSame($before, DB::table('migrations')->count());
    }

    public function test_partial_add_column_migration_is_retryable_without_erasing_values(): void
    {
        $id = DB::table('customers')->insertGetId(['name' => 'Preserved customer', 'phone' => '0770000000', 'created_at' => now(), 'updated_at' => now()]);
        $migration = require database_path('migrations/2026_07_25_000001_create_tax_management_tables.php');
        DB::table('tax_settings')->where('version', 1)->update(['invoice_prefix' => 'CUSTOM', 'next_invoice_number' => 789]);
        DB::table('customers')->where('id', $id)->update(['tin' => 'KEEP-123']);

        $migration->up();

        $this->assertDatabaseHas('customers', ['id' => $id, 'tin' => 'KEEP-123']);
        $this->assertDatabaseHas('tax_settings', ['version' => 1, 'invoice_prefix' => 'CUSTOM', 'next_invoice_number' => 789]);
        $this->assertSame([], app(MigrationHealthService::class)->check()['issues']);
    }

    public function test_retry_does_not_mark_new_preorder_items_as_previously_reserved(): void
    {
        $user = \App\Models\User::factory()->create();
        $customer = DB::table('customers')->insertGetId(['name' => 'Stock customer', 'phone' => '0770000001']);
        $unit = DB::table('units')->insertGetId(['name' => 'Piece', 'short_name' => 'pcs']);
        $product = DB::table('products')->insertGetId(['name' => 'Unreserved item', 'sku' => 'RETRY-1', 'unit_id' => $unit, 'cost_price' => 10, 'selling_price' => 20]);
        $order = DB::table('pre_orders')->insertGetId([
            'pre_order_number' => 'RETRY-PO', 'customer_id' => $customer, 'created_by' => $user->id,
            'pre_order_date' => '2026-10-18', 'vehicle_name' => 'Test vehicle', 'status' => 'pending',
        ]);
        $item = DB::table('pre_order_items')->insertGetId([
            'pre_order_id' => $order, 'product_id' => $product, 'original_product_name' => 'Unreserved item',
            'quantity' => 1, 'quoted_price' => 20, 'final_price' => 20, 'stock_deducted' => false,
        ]);
        $migration = require database_path('migrations/2026_10_17_040000_track_preorder_stock_reservations.php');
        $migration->up();
        $this->assertDatabaseHas('pre_order_items', ['id' => $item, 'stock_deducted' => false]);
    }
}

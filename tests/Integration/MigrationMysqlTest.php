<?php

namespace Tests\Integration;

use App\Services\MigrationHealthService;
use App\Support\Migrations\Metadata;
use App\Support\Migrations\Schema as MigrationSchema;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/** Opt-in integration test. All writes are confined to a uniquely named disposable database. */
class MigrationMysqlTest extends TestCase
{
    public function test_fresh_and_interrupted_mysql_upgrades_preserve_existing_data(): void
    {
        if (getenv('RUN_MYSQL_MIGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_MYSQL_MIGRATION_TESTS=1 to use a disposable local MySQL database.');
        }
        $config = config('database.connections.mysql');
        if (! in_array($config['host'], ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RuntimeException('Integration tests require a local MySQL server.');
        }
        $original = config('database.default');
        $name = 'spare_parts_migration_qa_'.date('YmdHis').'_'.bin2hex(random_bytes(3));
        $config['database'] = null;
        $config['url'] = null;
        config(['database.connections.migration_admin' => $config]);
        $admin = DB::connection('migration_admin');
        $admin->statement('CREATE DATABASE '.Metadata::quote($name).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            $config['database'] = $name;
            config(['database.connections.migration_qa' => $config, 'database.default' => 'migration_qa']);
            DB::setDefaultConnection('migration_qa');
            Schema::clearResolvedInstance('db.schema');
            $service = app(MigrationHealthService::class);
            $this->assertSame([], $service->migrate()['health']['issues']);
            $this->assertSame([], $service->check()['issues']);

            // Some shared hosts default new tables to MyISAM, which silently ignores FKs.
            // Only this newly installed table then fails all three relationship checks.
            Schema::drop('account_transfers');
            DB::table('migrations')->where('migration', '2026_10_17_030000_create_account_transfers_table')->delete();
            $previousEngine = DB::selectOne('SELECT @@session.default_storage_engine AS engine')->engine;
            DB::statement("SET SESSION default_storage_engine = 'MyISAM'");
            $retainedTransfer = null;
            Event::listen(MigrationEnded::class, function ($event) use (&$retainedTransfer, $name) {
                if (basename((new \ReflectionClass($event->migration))->getFileName()) !== '2026_10_17_030000_create_account_transfers_table.php') {
                    return;
                }
                $engine = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$name, 'account_transfers']);
                $this->assertSame('InnoDB', $engine->engine, 'New tables must override a MyISAM host default.');
                // Simulate a legacy migration/import creating a MyISAM table after
                // preflight, and ensure the final repair retains its existing rows.
                DB::statement('ALTER TABLE account_transfers ENGINE=MyISAM');
                $this->assertContains(
                    'Unsupported table engine: account_transfers (MyISAM). InnoDB is required for reliable foreign keys.',
                    app(MigrationHealthService::class)->check()['issues']
                );
                $retainedTransfer = DB::table('account_transfers')->insertGetId([
                    'request_token' => '00000000-0000-0000-0000-000000000003',
                    'transfer_date' => '2026-10-18', 'amount' => 123.45, 'note' => 'Keep transfer history',
                ]);
            });
            try {
                $engineUpgrade = $service->migrate();
            } finally {
                Event::forget(MigrationEnded::class);
                DB::statement('SET SESSION default_storage_engine = '.DB::connection()->getPdo()->quote($previousEngine));
            }
            $this->assertSame([], $engineUpgrade['health']['issues']);
            $engine = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$name, 'account_transfers']);
            $this->assertSame('InnoDB', $engine->engine);
            $this->assertCount(3, Metadata::foreignKeys(DB::connection(), 'account_transfers'));
            $this->assertTrue(collect($engineUpgrade['repairs'])->contains('Converted to InnoDB: account_transfers'));
            $savedTransfer = DB::table('account_transfers')->where('id', $retainedTransfer)->first();
            $this->assertSame('123.45', $savedTransfer->amount);
            $this->assertSame('Keep transfer history', $savedTransfer->note);

            $customer = DB::table('customers')->insertGetId(['name' => 'Preserve me', 'phone' => '0771234567', 'opening_balance' => 5678.90]);

            // The reported production failure: stores is absent although its migration
            // is recorded, and sales.store_id exists after a failed FK statement.
            Schema::table('sales', fn (Blueprint $table) => $table->dropForeign(['store_id']));
            Schema::disableForeignKeyConstraints();
            try {
                Schema::drop('stores');
            } finally {
                Schema::enableForeignKeyConstraints();
            }
            DB::table('migrations')->where('migration', '2026_06_05_000003_add_accounting_fields_to_documents')->delete();
            $this->assertContains('Missing table: stores', $service->check()['issues']);
            $result = $service->migrate();
            $this->assertSame([], $result['health']['issues']);
            $this->assertTrue(Schema::hasTable('stores'));
            $this->assertSame('5678.90', DB::table('customers')->where('id', $customer)->value('opening_balance'));
            $this->assertTrue(collect(Metadata::foreignKeys(DB::connection(), 'sales'))->contains('name', 'sales_store_id_foreign'));

            // Resume a partially committed product-price migration without dropping data.
            $unit = DB::table('units')->insertGetId(['name' => 'Piece', 'short_name' => 'pc']);
            $product = DB::table('products')->insertGetId(['name' => 'Preserved product', 'sku' => 'KEEP-1', 'unit_id' => $unit]);
            $price = DB::table('product_prices')->insertGetId(['product_id' => $product, 'cost_price' => 10, 'selling_price' => 25]);
            $user = DB::table('users')->insertGetId(['name' => 'Test', 'email' => 'migration@example.test', 'password' => 'unused']);
            $supplier = DB::table('suppliers')->insertGetId(['name' => 'Test supplier', 'phone' => '0770000000']);
            $purchase = DB::table('purchases')->insertGetId(['purchase_no' => 'KEEP-PUR', 'supplier_id' => $supplier, 'user_id' => $user, 'purchase_date' => '2026-10-18']);
            $item = DB::table('purchase_items')->insertGetId(['purchase_id' => $purchase, 'product_id' => $product, 'product_price_id' => $price, 'quantity' => 1, 'unit_cost' => 10, 'selling_price' => 25, 'total' => 10]);
            Schema::table('purchase_items', fn (Blueprint $table) => $table->dropForeign(['product_price_id']));
            DB::table('migrations')->where('migration', '2026_05_16_000002_add_product_price_id_to_stock_documents')->delete();
            $service->migrate();
            $saved = DB::table('purchase_items')->where('id', $item)->first();
            $this->assertSame($price, $saved->product_price_id);
            $this->assertSame('25.00', $saved->selling_price);
            $this->assertSame([], $service->check()['issues']);

            // Multi-table migrations can resume after some CREATE/ALTER statements committed.
            DB::table('tax_settings')->where('version', 1)->update(['invoice_prefix' => 'KEEP', 'next_invoice_number' => 321]);
            DB::table('settings')->where('key', 'pos_cheque_auto_pass_enabled')->update(['value' => '0']);
            foreach (['2026_06_05_000001_create_quickbooks_accounting_tables', '2026_06_06_000001_create_cheque_payments_table', '2026_07_25_000001_create_tax_management_tables', '2026_09_01_000433_update_cheque_payments_and_purchases_for_multi_pay'] as $migration) {
                DB::table('migrations')->where('migration', $migration)->delete();
            }
            $service->migrate();
            $this->assertSame('KEEP', DB::table('tax_settings')->where('version', 1)->value('invoice_prefix'));
            $this->assertSame(321, DB::table('tax_settings')->where('version', 1)->value('next_invoice_number'));
            $this->assertSame('0', DB::table('settings')->where('key', 'pos_cheque_auto_pass_enabled')->value('value'));

            // A missing column in an already-Ran migration is repaired and verified.
            Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('phone'));
            $this->assertContains('Missing column: stores.phone', $service->check()['issues']);
            $service->migrate();
            $this->assertSame([], $service->check()['issues']);
            $this->assertSame([], $service->migrate()['repairs']);

            DB::statement('ALTER TABLE settings MODIFY id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE settings DROP PRIMARY KEY');
            $this->assertContains('Missing AUTO_INCREMENT: settings.id', $service->check()['issues']);
            $service->migrate();
            $this->assertSame([], $service->check()['issues']);

            // Imported later child columns can precede a pending parent migration.
            // Reproduce the reported store_transfers FK before its creator runs.
            $from = DB::table('stores')->insertGetId(['name' => 'From store', 'code' => 'FROM']);
            $to = DB::table('stores')->insertGetId(['name' => 'To store', 'code' => 'TO']);
            $transfer = DB::table('store_stock_transfers')->insertGetId([
                'from_store_id' => $from, 'to_store_id' => $to, 'product_id' => $product,
                'quantity' => 3, 'transfer_date' => '2026-10-18', 'notes' => 'Keep this movement',
            ]);
            Schema::table('store_stock_transfers', fn (Blueprint $table) => $table->dropForeign(['store_transfer_id']));
            Schema::drop('store_transfers');
            Schema::disableForeignKeyConstraints();
            try {
                Schema::drop('bank_accounts');
            } finally {
                Schema::enableForeignKeyConstraints();
            }
            DB::table('migrations')->whereIn('migration', [
                '2026_06_05_000001_create_quickbooks_accounting_tables',
                '2026_06_05_000002_create_store_shipment_stock_tables',
                '2026_06_16_180717_create_store_transfers_table',
            ])->delete();
            $earlyForeignKeyAttempted = false;
            Event::listen(MigrationStarted::class, function () use (&$earlyForeignKeyAttempted) {
                if (! $earlyForeignKeyAttempted) {
                    $this->assertTrue(Schema::hasTable('store_transfers'), 'Missing parents must be restored before any migration begins.');
                    $this->assertTrue(Schema::hasTable('bank_accounts'), 'Bank dependencies must also be restored before any migration begins.');
                    MigrationSchema::table('store_stock_transfers', fn (Blueprint $table) => $table->foreign('store_transfer_id')->references('id')->on('store_transfers')->cascadeOnDelete());
                    $earlyForeignKeyAttempted = true;
                }
            });
            try {
                $result = $service->migrate();
            } finally {
                Event::forget(MigrationStarted::class);
            }
            $this->assertTrue($earlyForeignKeyAttempted);
            $this->assertSame([], $result['health']['issues']);
            $this->assertTrue(collect($result['repairs'])->contains(fn ($repair) => str_contains($repair, 'Created missing parent table: store_transfers')));
            $this->assertTrue(collect($result['repairs'])->contains(fn ($repair) => str_contains($repair, 'Created missing parent table: bank_accounts')));
            $this->assertTrue(collect(Metadata::foreignKeys(DB::connection(), 'store_stock_transfers'))->contains('name', 'store_stock_transfers_store_transfer_id_foreign'));
            $savedTransfer = DB::table('store_stock_transfers')->where('id', $transfer)->first();
            $this->assertSame('3.000', $savedTransfer->quantity);
            $this->assertSame('Keep this movement', $savedTransfer->notes);
            $this->assertFalse(MigrationSchema::foreignKeysDeferred());

            // An orphan reference must be reported, never silently deleted or reset.
            $store = DB::table('stores')->insertGetId(['name' => 'Test store', 'code' => 'TEST']);
            Schema::table('product_store_exclusions', fn (Blueprint $table) => $table->dropForeign(['product_id']));
            $orphan = DB::table('product_store_exclusions')->insertGetId(['product_id' => 999999, 'store_id' => $store]);
            try {
                $service->migrate();
                $this->fail('An orphan reference must stop the repair.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('1 existing rows reference missing products records', $exception->getMessage());
            }
            $this->assertSame(999999, DB::table('product_store_exclusions')->where('id', $orphan)->value('product_id'));
            $this->assertFalse(MigrationSchema::foreignKeysDeferred());
            $this->assertStringContainsString('Nothing to migrate', $service->progress()['output']);
        } finally {
            DB::disconnect('migration_qa');
            config(['database.default' => $original]);
            DB::setDefaultConnection($original);
            Schema::clearResolvedInstance('db.schema');
            // The name is generated above; never accept a configured business DB here.
            $admin->statement('DROP DATABASE '.Metadata::quote($name));
            DB::disconnect('migration_admin');
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 17)->startOfDay());
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
    }

    public function test_dashboard_cards_open_reports_for_the_selected_period(): void
    {
        $dates = ['from' => '2026-10-01', 'to' => '2026-10-16'];
        $response = $this->get(route('dashboard', ['start_date' => $dates['from'], 'end_date' => $dates['to']]))->assertOk();

        foreach (['sales', 'purchase', 'profit-loss', 'due-bills', 'expense'] as $report) {
            $url = route('reports.'.$report, $dates);
            $response->assertSee('href="'.e($url).'"', false);
            $this->get($url)->assertOk()->assertViewHas('from', $dates['from'])->assertViewHas('to', $dates['to']);
        }

        $response->assertSee('href="'.e(route('sales.index', ['date_from' => $dates['from'], 'date_to' => $dates['to']])).'"', false);
        foreach (['pos.index', 'purchases.create', 'products.create', 'expenses.create', 'customers.create', 'suppliers.create'] as $action) {
            $response->assertSee('href="'.route($action).'"', false);
        }
    }

    public function test_dashboard_default_links_use_today_and_product_and_invoice_rows_open_details(): void
    {
        $product = $this->product('Brake Pad', 0);
        $sale = Sale::create([
            'sale_no' => 'INV-DASHBOARD-1', 'user_id' => auth()->id(), 'sale_date' => today(),
            'subtotal' => 100, 'total_amount' => 100, 'due_amount' => 100, 'sale_type' => 'sale',
        ]);
        $sale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'total' => 100]);

        $response = $this->get(route('dashboard'))->assertOk()->assertViewHas('totalSales', 100);
        $response->assertSee('href="'.e(route('reports.sales', ['from' => '2026-10-17', 'to' => '2026-10-17'])).'"', false);
        $response->assertSee('href="'.route('products.show', $product).'"', false);
        $response->assertSee('href="'.route('sales.show', $sale).'"', false);
        $response->assertSee('href="'.e(route('reports.trending', ['from' => '2026-09-17', 'to' => '2026-10-17'])).'"', false);
        $this->get(route('products.show', $product))->assertOk();
        $this->get(route('sales.show', $sale))->assertOk();
    }

    public function test_stock_drilldown_matches_dashboard_and_preserves_scope_in_exports(): void
    {
        $regular = $this->product('Regular Part', 0);
        $preorder = $this->product('Preorder Part', 1, true);
        $this->product('Well Stocked Part', 20);
        $this->product('Inactive Part', 0, false, false);
        $filters = ['all_product_types' => 1, 'active_only' => 1, 'low_stock' => 1];

        $dashboard = $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('totalProducts', 3)->assertViewHas('lowStockItems', 2);
        $dashboard->assertSee('href="'.e(route('reports.stock', $filters)).'"', false);

        $this->get(route('reports.stock', $filters))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total_products'] === 2 && $summary['low_stock'] === 2)
            ->assertViewHas('paginator', fn ($paginator) => $paginator->pluck('id')->sort()->values()->all() === [$regular->id, $preorder->id])
            ->assertSee('name="all_product_types" value="1"', false)
            ->assertSee('name="active_only" value="1"', false);

        $csv = $this->get(route('reports.stock.csv', $filters))->assertOk()->getContent();
        $this->assertStringContainsString('Regular Part', $csv);
        $this->assertStringContainsString('Preorder Part', $csv);
        $this->assertStringNotContainsString('Inactive Part', $csv);
        $this->assertStringNotContainsString('Well Stocked Part', $csv);

        // Existing stock report entry points still keep regular and preorder stock separate.
        $this->get(route('reports.stock'))->assertOk()
            ->assertViewHas('paginator', fn ($paginator) => ! $paginator->pluck('id')->contains($preorder->id));
        $this->get(route('reports.stock', ['is_pre_order' => 1]))->assertOk()
            ->assertViewHas('paginator', fn ($paginator) => $paginator->pluck('id')->all() === [$preorder->id]);
    }

    private function product(string $name, int $stock, bool $preorder = false, bool $active = true): Product
    {
        $unit = Unit::firstOrCreate(['name' => 'Piece'], ['short_name' => 'pc', 'base_unit_multiplier' => 1, 'is_active' => true]);

        return Product::create([
            'name' => $name, 'sku' => str_replace(' ', '-', $name), 'unit_id' => $unit->id,
            'cost_price' => 50, 'selling_price' => 100, 'stock_quantity' => $stock,
            'alert_quantity' => 1, 'is_pre_order' => $preorder, 'is_active' => $active,
        ]);
    }
}

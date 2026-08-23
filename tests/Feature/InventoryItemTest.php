<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Branch $branch;
    protected Branch $otherBranch;
    protected InventoryCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['user_type' => 'super_admin']);

        $restaurant = Restaurant::create([
            'name' => 'Main Restaurant',
            'slug' => 'main-restaurant',
            'phone' => '1234567890',
            'email' => 'restaurant@example.com',
            'address' => '123 Main St',
            'postcode' => '12345',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Downtown Branch',
            'status' => 'active',
        ]);

        $this->otherBranch = Branch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Uptown Branch',
            'status' => 'active',
        ]);

        $this->category = InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Raw Meat',
        ]);
    }

    public function test_can_create_inventory_item_with_derived_status(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/api/v1/inventory-items', [
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'type' => 'raw_material',
            'name' => 'Minced Beef',
            'quantity' => 5,
            'minimum_stock' => 10,
            'unit' => 'kg',
            'purchase_price' => 4.5,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'low_stock', 'name' => 'Minced Beef']);
    }

    public function test_summary_reports_counts_and_total_value(): void
    {
        InventoryItem::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->category->id,
            'name' => 'Item A', 'quantity' => 10, 'minimum_stock' => 2, 'unit' => 'kg',
            'purchase_price' => 5, 'status' => 'in_stock',
        ]);
        InventoryItem::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->category->id,
            'name' => 'Item B', 'quantity' => 0, 'minimum_stock' => 2, 'unit' => 'kg',
            'purchase_price' => 3, 'status' => 'out_of_stock',
        ]);
        InventoryItem::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->category->id,
            'name' => 'Item C', 'quantity' => 1, 'minimum_stock' => 5, 'unit' => 'kg',
            'purchase_price' => 2, 'status' => 'low_stock',
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/api/v1/inventory-items/summary');

        $response->assertStatus(200)->assertJson([
            'total_items' => 3,
            'low_stock_items' => 1,
            'out_of_stock_items' => 1,
            'total_stock_value' => 52.0,
        ]);
    }

    public function test_distribute_moves_stock_between_branches(): void
    {
        $item = InventoryItem::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->category->id,
            'name' => 'Buns', 'quantity' => 100, 'minimum_stock' => 10, 'unit' => 'pcs',
            'purchase_price' => 0.5, 'status' => 'in_stock',
        ]);

        $response = $this->actingAs($this->adminUser)->postJson("/api/v1/inventory-items/{$item->id}/distribute", [
            'branch_id' => $this->otherBranch->id,
            'quantity' => 30,
        ]);

        $response->assertStatus(200);

        $this->assertEquals(70, $item->fresh()->quantity);

        $targetItem = InventoryItem::where('branch_id', $this->otherBranch->id)->where('name', 'Buns')->first();
        $this->assertNotNull($targetItem);
        $this->assertEquals(30, $targetItem->quantity);
    }

    public function test_distribute_fails_when_insufficient_stock(): void
    {
        $item = InventoryItem::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->category->id,
            'name' => 'Buns', 'quantity' => 5, 'minimum_stock' => 10, 'unit' => 'pcs',
            'purchase_price' => 0.5, 'status' => 'low_stock',
        ]);

        $response = $this->actingAs($this->adminUser)->postJson("/api/v1/inventory-items/{$item->id}/distribute", [
            'branch_id' => $this->otherBranch->id,
            'quantity' => 30,
        ]);

        $response->assertStatus(422);
        $this->assertEquals(5, $item->fresh()->quantity);
    }

    public function test_export_returns_csv(): void
    {
        InventoryItem::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->category->id,
            'name' => 'Item A', 'quantity' => 10, 'minimum_stock' => 2, 'unit' => 'kg',
            'purchase_price' => 5, 'status' => 'in_stock',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/api/v1/inventory-items/export');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Item A', $response->streamedContent());
    }

    public function test_analytics_reports_kpis_for_period(): void
    {
        $raw = InventoryItem::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->category->id,
            'name' => 'Minced Beef', 'quantity' => 100, 'minimum_stock' => 10, 'unit' => 'kg',
            'purchase_price' => 4, 'status' => 'in_stock',
        ]);

        $this->actingAs($this->adminUser)->postJson('/api/v1/inventory-transactions', [
            'inventory_item_id' => $raw->id,
            'transaction_type' => 'sale',
            'quantity' => 20,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/api/v1/inventory-items/analytics?period=week');

        $response->assertStatus(200)
            ->assertJson([
                'period' => 'week',
                'top_selling_product' => ['id' => $raw->id, 'name' => 'Minced Beef', 'units_sold' => 20.0],
            ]);
    }

    public function test_convert_uses_prepared_items_stored_ratio(): void
    {
        $raw = InventoryItem::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->category->id,
            'type' => 'raw_material', 'name' => 'Minced Beef', 'quantity' => 50,
            'minimum_stock' => 5, 'unit' => 'kg', 'purchase_price' => 4, 'status' => 'in_stock',
        ]);

        $prepared = InventoryItem::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->category->id,
            'type' => 'prepared', 'name' => 'Burger Patties', 'quantity' => 0,
            'minimum_stock' => 10, 'unit' => 'pcs', 'purchase_price' => 1, 'status' => 'out_of_stock',
            'made_from_item_id' => $raw->id, 'pack_size' => 7, 'pack_unit' => 'kg',
            'yield_qty' => 50, 'yield_unit' => 'pcs',
        ]);

        $response = $this->actingAs($this->adminUser)->postJson('/api/v1/stock-conversions/convert', [
            'prepared_item_id' => $prepared->id,
            'packs' => 2,
        ]);

        $response->assertStatus(200)->assertJson([
            'raw_consumed' => 14.0,
            'yield_produced' => 100.0,
        ]);

        $this->assertEquals(36, $raw->fresh()->quantity);
        $this->assertEquals(100, $prepared->fresh()->quantity);
        $this->assertEquals('in_stock', $prepared->fresh()->status);
    }

    public function test_convert_rejects_when_no_ratio_configured(): void
    {
        $prepared = InventoryItem::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->category->id,
            'type' => 'prepared', 'name' => 'Burger Patties', 'quantity' => 0,
            'minimum_stock' => 10, 'unit' => 'pcs', 'purchase_price' => 1, 'status' => 'out_of_stock',
        ]);

        $response = $this->actingAs($this->adminUser)->postJson('/api/v1/stock-conversions/convert', [
            'prepared_item_id' => $prepared->id,
            'packs' => 2,
        ]);

        $response->assertStatus(422);
    }
}

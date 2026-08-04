<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Restaurant;
use App\Models\StockConversion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockConversionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Branch $branch;
    protected InventoryItem $sourceItem;
    protected InventoryItem $targetItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'user_type' => 'super_admin',
        ]);

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

        $category = InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Raw Meat',
        ]);

        $this->sourceItem = InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'name' => 'Whole Beef Slab',
            'quantity' => 100.0,
            'unit' => 'kg',
            'purchase_price' => 10.00,
        ]);

        $this->targetItem = InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'name' => 'Burger Patties',
            'quantity' => 0.0,
            'unit' => 'pcs',
            'purchase_price' => 2.00,
        ]);
    }

    public function test_can_list_stock_conversions(): void
    {
        StockConversion::create([
            'branch_id' => $this->branch->id,
            'inventory_item_id' => $this->sourceItem->id,
            'converted_item_id' => $this->targetItem->id,
            'quantity' => 10.0,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/stock-conversions');

        $response->assertStatus(200)
            ->assertJsonFragment(['quantity' => 10.0]);
    }

    public function test_can_filter_stock_conversions_by_branch_and_item(): void
    {
        StockConversion::create([
            'branch_id' => $this->branch->id,
            'inventory_item_id' => $this->sourceItem->id,
            'converted_item_id' => $this->targetItem->id,
            'quantity' => 15.0,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/stock-conversions?branch_id={$this->branch->id}&inventory_item_id={$this->sourceItem->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['quantity' => 15.0]);
    }

    public function test_can_create_stock_conversion(): void
    {
        $payload = [
            'branch_id' => $this->branch->id,
            'inventory_item_id' => $this->sourceItem->id,
            'converted_item_id' => $this->targetItem->id,
            'quantity' => 25.5,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/stock-conversions', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['quantity' => 25.5]);

        $this->assertDatabaseHas('stock_conversions', [
            'branch_id' => $this->branch->id,
            'inventory_item_id' => $this->sourceItem->id,
            'quantity' => 25.5,
        ]);
    }

    public function test_can_show_stock_conversion(): void
    {
        $conversion = StockConversion::create([
            'branch_id' => $this->branch->id,
            'inventory_item_id' => $this->sourceItem->id,
            'converted_item_id' => $this->targetItem->id,
            'quantity' => 5.0,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/stock-conversions/{$conversion->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['quantity' => 5.0]);
    }

    public function test_can_update_stock_conversion(): void
    {
        $conversion = StockConversion::create([
            'branch_id' => $this->branch->id,
            'inventory_item_id' => $this->sourceItem->id,
            'converted_item_id' => $this->targetItem->id,
            'quantity' => 5.0,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/stock-conversions/{$conversion->id}", [
                'quantity' => 8.0,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['quantity' => 8.0]);

        $this->assertDatabaseHas('stock_conversions', [
            'id' => $conversion->id,
            'quantity' => 8.0,
        ]);
    }

    public function test_can_delete_stock_conversion(): void
    {
        $conversion = StockConversion::create([
            'branch_id' => $this->branch->id,
            'inventory_item_id' => $this->sourceItem->id,
            'converted_item_id' => $this->targetItem->id,
            'quantity' => 5.0,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/stock-conversions/{$conversion->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('stock_conversions', ['id' => $conversion->id]);
    }

    public function test_can_calculate_conversion_estimate(): void
    {
        $payload = [
            'inventory_item_id' => $this->sourceItem->id,
            'converted_item_id' => $this->targetItem->id,
            'quantity' => 12.0,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/stock-conversions/calculate', $payload);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Branch $branch;

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
    }

    public function test_can_list_inventory_categories_with_items_count(): void
    {
        $category = InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Raw Meat',
        ]);

        InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'name' => 'Beef Patty',
            'quantity' => 10,
            'minimum_stock' => 2,
            'unit' => 'kg',
            'purchase_price' => 12.50,
            'selling_price' => 15.00,
            'status' => 'in_stock',
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/api/v1/inventory-categories');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Raw Meat',
            'items_count' => 1,
        ]);
    }
}

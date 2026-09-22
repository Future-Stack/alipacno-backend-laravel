<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Recipe;
use App\Models\Restaurant;
use App\Services\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryStockSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $restaurant;
    protected $branch;
    protected $category;
    protected $inventoryItem;
    protected $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::first() ?? Restaurant::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'phone' => '1234567890',
            'email' => 'test_rest@example.com',
            'address' => '123 Test St',
            'postcode' => 'TE1 1ST',
        ]);

        $this->branch = Branch::first() ?? Branch::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Main Test Branch',
            'branch_code' => 'BR-TEST-' . uniqid(),
            'phone' => '1234567890',
            'email' => 'test_branch@example.com',
            'address' => '123 Test St',
            'postcode' => 'TE1 1ST',
        ]);

        $this->category = InventoryCategory::first() ?? InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'General Supplies',
        ]);

        $this->inventoryItem = InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'name' => 'Special Pizza Dough ' . uniqid(),
            'quantity' => 10,
            'minimum_stock' => 2,
            'unit' => 'kg',
            'purchase_price' => 5.00,
            'status' => 'in_stock',
        ]);

        $menuCategory = \App\Models\Category::first() ?? \App\Models\Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Main Category',
            'slug' => 'main-category',
        ]);

        $this->menuItem = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'category_id' => $menuCategory->id,
            'name' => 'Margherita Pizza ' . uniqid(),
            'slug' => 'margherita-pizza-' . uniqid(),
            'price' => 12.00,
            'status' => 'available',
        ]);
    }

    public function test_stock_validation_passes_when_sufficient(): void
    {
        // Link via Recipe: 1 Pizza requires 2 kg Dough
        $recipe = Recipe::create([
            'menu_item_id' => $this->menuItem->id,
            'branch_id' => $this->branch->id,
            'name' => 'Margherita Recipe',
        ]);

        $recipe->ingredients()->create([
            'inventory_item_id' => $this->inventoryItem->id,
            'quantity' => 2,
            'unit' => 'kg',
        ]);

        // Request 3 pizzas = 6 kg dough <= 10 kg available -> should pass without throwing
        InventoryStockService::validateStockForItems($this->branch->id, [
            ['menu_item_id' => $this->menuItem->id, 'quantity' => 3],
        ]);

        $this->assertTrue(true);
    }

    public function test_stock_validation_throws_when_insufficient(): void
    {
        $this->expectException(ValidationException::class);

        // Link via Recipe: 1 Pizza requires 2 kg Dough
        $recipe = Recipe::create([
            'menu_item_id' => $this->menuItem->id,
            'branch_id' => $this->branch->id,
            'name' => 'Margherita Recipe',
        ]);

        $recipe->ingredients()->create([
            'inventory_item_id' => $this->inventoryItem->id,
            'quantity' => 2,
            'unit' => 'kg',
        ]);

        // Request 6 pizzas = 12 kg dough > 10 kg available -> should throw ValidationException
        InventoryStockService::validateStockForItems($this->branch->id, [
            ['menu_item_id' => $this->menuItem->id, 'quantity' => 6],
        ]);
    }

    public function test_automatic_stock_deduction_on_order_completion(): void
    {
        $recipe = Recipe::create([
            'menu_item_id' => $this->menuItem->id,
            'branch_id' => $this->branch->id,
            'name' => 'Margherita Recipe',
        ]);

        $recipe->ingredients()->create([
            'inventory_item_id' => $this->inventoryItem->id,
            'quantity' => 2,
            'unit' => 'kg',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-TEST-' . uniqid(),
            'branch_id' => $this->branch->id,
            'order_type' => 'delivery',
            'order_status' => 'pending',
            'total' => 36.00,
        ]);

        $order->items()->create([
            'menu_item_id' => $this->menuItem->id,
            'item_name' => $this->menuItem->name,
            'quantity' => 2, // 2 * 2kg = 4kg
            'unit_price' => 12.00,
            'subtotal' => 24.00,
        ]);

        $deducted = InventoryStockService::deductStockForOrder($order);
        $this->assertTrue($deducted);

        $this->inventoryItem->refresh();
        $this->assertEquals(6.0, (float) $this->inventoryItem->quantity); // 10 - 4 = 6
        $this->assertEquals('in_stock', $this->inventoryItem->status);

        // Verify transaction record
        $tx = InventoryTransaction::where('inventory_item_id', $this->inventoryItem->id)
            ->where('transaction_type', 'sale')
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals(4.0, (float) $tx->quantity);

        // Test idempotency: calling deduct again should return false and not deduct
        $secondDeduction = InventoryStockService::deductStockForOrder($order);
        $this->assertFalse($secondDeduction);
        $this->inventoryItem->refresh();
        $this->assertEquals(6.0, (float) $this->inventoryItem->quantity);
    }

    public function test_direct_item_stock_validation_and_deduction_without_recipe(): void
    {
        // Direct matching: Menu item with same name as an inventory item in that branch
        $directInventoryItem = InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'name' => 'Coca Cola Can',
            'quantity' => 5,
            'minimum_stock' => 1,
            'unit' => 'can',
            'purchase_price' => 0.50,
            'status' => 'in_stock',
        ]);

        $directMenuItem = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->menuItem->category_id,
            'name' => 'Coca Cola Can',
            'slug' => 'coca-cola-can-' . uniqid(),
            'price' => 2.00,
            'status' => 'available',
        ]);

        // Request 3 cans <= 5 available -> should pass
        InventoryStockService::validateStockForItems($this->branch->id, [
            ['menu_item_id' => $directMenuItem->id, 'quantity' => 3],
        ]);

        // Request 10 cans > 5 available -> should fail
        $this->expectException(ValidationException::class);
        InventoryStockService::validateStockForItems($this->branch->id, [
            ['menu_item_id' => $directMenuItem->id, 'quantity' => 10],
        ]);
    }
}

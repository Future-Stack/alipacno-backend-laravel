<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Branch $branch;
    protected MenuItem $menuItem;
    protected InventoryItem $inventoryItem;

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

        $category = Category::create([
            'branch_id' => $this->branch->id,
            'name' => 'Pizzas',
            'slug' => 'pizzas',
        ]);

        $this->menuItem = MenuItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'name' => 'Margherita Pizza',
            'slug' => 'margherita-pizza',
            'price' => 12.99,
        ]);

        $invCategory = InventoryCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Dairy',
        ]);
        $this->inventoryItem = InventoryItem::create([
            'branch_id' => $this->branch->id,
            'category_id' => $invCategory->id,
            'name' => 'Mozzarella Cheese',
            'sku' => 'CHEESE-001',
            'quantity' => 100,
            'unit' => 'kg',
            'purchase_price' => 5.00,
        ]);
    }

    public function test_can_list_recipes(): void
    {
        Recipe::create([
            'name' => 'Margherita Standard Recipe',
            'menu_item_id' => $this->menuItem->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/recipes');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Margherita Standard Recipe']);
    }

    public function test_can_filter_recipes_by_branch_and_menu_item(): void
    {
        $recipe = Recipe::create([
            'name' => 'Target Recipe',
            'menu_item_id' => $this->menuItem->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/recipes?branch_id={$this->branch->id}&menu_item_id={$this->menuItem->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Target Recipe']);
    }

    public function test_can_create_recipe_with_ingredients(): void
    {
        $payload = [
            'name' => 'Pepperoni Special Recipe',
            'menu_item_id' => $this->menuItem->id,
            'branch_id' => $this->branch->id,
            'ingredients' => [
                [
                    'inventory_item_id' => $this->inventoryItem->id,
                    'quantity' => 0.25,
                    'unit' => 'kg',
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/recipes', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Pepperoni Special Recipe']);

        $this->assertDatabaseHas('recipes', [
            'name' => 'Pepperoni Special Recipe',
            'menu_item_id' => $this->menuItem->id,
        ]);

        $this->assertDatabaseHas('recipe_ingredients', [
            'inventory_item_id' => $this->inventoryItem->id,
            'quantity' => 0.25,
            'unit' => 'kg',
        ]);
    }

    public function test_can_show_recipe(): void
    {
        $recipe = Recipe::create([
            'name' => 'Detailed Recipe',
            'menu_item_id' => $this->menuItem->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/recipes/{$recipe->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Detailed Recipe']);
    }

    public function test_can_update_recipe_and_sync_ingredients(): void
    {
        $recipe = Recipe::create([
            'name' => 'Old Recipe Name',
            'menu_item_id' => $this->menuItem->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/recipes/{$recipe->id}", [
                'name' => 'Updated Recipe Name',
                'ingredients' => [
                    [
                        'inventory_item_id' => $this->inventoryItem->id,
                        'quantity' => 0.50,
                        'unit' => 'kg',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Recipe Name']);

        $this->assertDatabaseHas('recipes', [
            'id' => $recipe->id,
            'name' => 'Updated Recipe Name',
        ]);

        $this->assertDatabaseHas('recipe_ingredients', [
            'recipe_id' => $recipe->id,
            'inventory_item_id' => $this->inventoryItem->id,
            'quantity' => 0.50,
        ]);
    }

    public function test_can_delete_recipe(): void
    {
        $recipe = Recipe::create([
            'name' => 'To Be Deleted',
            'menu_item_id' => $this->menuItem->id,
            'branch_id' => $this->branch->id,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'inventory_item_id' => $this->inventoryItem->id,
            'quantity' => 0.10,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/recipes/{$recipe->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_ingredients', ['recipe_id' => $recipe->id]);
    }

    public function test_can_add_individual_ingredient(): void
    {
        $recipe = Recipe::create([
            'name' => 'Recipe for Single Ingredient Add',
            'menu_item_id' => $this->menuItem->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/recipes/{$recipe->id}/ingredients", [
                'inventory_item_id' => $this->inventoryItem->id,
                'quantity' => 0.30,
                'unit' => 'kg',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['quantity' => 0.30]);

        $this->assertDatabaseHas('recipe_ingredients', [
            'recipe_id' => $recipe->id,
            'inventory_item_id' => $this->inventoryItem->id,
            'quantity' => 0.30,
        ]);
    }

    public function test_can_remove_individual_ingredient(): void
    {
        $recipe = Recipe::create([
            'name' => 'Recipe for Single Ingredient Remove',
            'menu_item_id' => $this->menuItem->id,
            'branch_id' => $this->branch->id,
        ]);

        $ingredient = RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'inventory_item_id' => $this->inventoryItem->id,
            'quantity' => 0.15,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/recipes/{$recipe->id}/ingredients/{$ingredient->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('recipe_ingredients', [
            'id' => $ingredient->id,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Topping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToppingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Restaurant $restaurant;
    protected MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'user_type' => 'super_admin',
        ]);

        $this->restaurant = Restaurant::create([
            'name' => 'Main Restaurant',
            'slug' => 'main-restaurant',
            'phone' => '1234567890',
            'email' => 'restaurant@example.com',
            'address' => '123 Main St',
            'postcode' => '12345',
            'status' => 'active',
        ]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Pizzas',
            'slug' => 'pizzas',
        ]);

        $this->menuItem = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name' => 'Pepperoni Pizza',
            'slug' => 'pepperoni-pizza',
            'price' => 12.99,
        ]);
    }

    public function test_can_list_toppings(): void
    {
        Topping::create([
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Extra Cheese',
            'price' => 1.50,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/toppings');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Extra Cheese']);
    }

    public function test_can_filter_toppings_by_restaurant_and_menu_item(): void
    {
        Topping::create([
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Mushrooms',
            'price' => 1.00,
        ]);

        Topping::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Jalapenos',
            'price' => 0.75,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/toppings?menu_item_id={$this->menuItem->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Mushrooms'])
            ->assertJsonMissing(['name' => 'Jalapenos']);
    }

    public function test_can_create_topping(): void
    {
        $payload = [
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Black Olives',
            'price' => 1.25,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/toppings', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Black Olives']);

        $this->assertDatabaseHas('toppings', [
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Black Olives',
            'price' => 1.25,
        ]);
    }

    public function test_can_show_topping(): void
    {
        $topping = Topping::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Bacon Bits',
            'price' => 2.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/toppings/{$topping->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Bacon Bits']);
    }

    public function test_can_update_topping(): void
    {
        $topping = Topping::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Old Topping Name',
            'price' => 1.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/toppings/{$topping->id}", [
                'name' => 'Updated Topping Name',
                'price' => 1.75,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Topping Name']);

        $this->assertDatabaseHas('toppings', [
            'id' => $topping->id,
            'name' => 'Updated Topping Name',
            'price' => 1.75,
        ]);
    }

    public function test_can_delete_topping(): void
    {
        $topping = Topping::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Temp Topping',
            'price' => 0.50,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/toppings/{$topping->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('toppings', ['id' => $topping->id]);
    }

    public function test_can_bulk_create_toppings(): void
    {
        $payload = [
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'toppings' => [
                ['name' => 'Pineapple', 'price' => 1.50],
                ['name' => 'Ham', 'price' => 2.00],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/toppings/bulk', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('toppings', [
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Pineapple',
        ]);

        $this->assertDatabaseHas('toppings', [
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Ham',
        ]);
    }
}

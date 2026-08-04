<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CookingPreference;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookingPreferenceTest extends TestCase
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
            'name' => 'Steaks',
            'slug' => 'steaks',
        ]);

        $this->menuItem = MenuItem::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name' => 'Ribeye Steak',
            'slug' => 'ribeye-steak',
            'price' => 24.99,
        ]);
    }

    public function test_can_list_cooking_preferences(): void
    {
        CookingPreference::create([
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Medium Rare',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/cooking-preferences');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Medium Rare']);
    }

    public function test_can_filter_cooking_preferences_by_restaurant_and_menu_item(): void
    {
        CookingPreference::create([
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Rare',
        ]);

        CookingPreference::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Well Done',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/cooking-preferences?menu_item_id={$this->menuItem->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Rare'])
            ->assertJsonMissing(['name' => 'Well Done']);
    }

    public function test_can_create_cooking_preference(): void
    {
        $payload = [
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Extra Crispy',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/cooking-preferences', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Extra Crispy']);

        $this->assertDatabaseHas('cooking_preferences', [
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Extra Crispy',
        ]);
    }

    public function test_can_show_cooking_preference(): void
    {
        $preference = CookingPreference::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'No Sauce',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/cooking-preferences/{$preference->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'No Sauce']);
    }

    public function test_can_update_cooking_preference(): void
    {
        $preference = CookingPreference::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Old Preference',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/cooking-preferences/{$preference->id}", [
                'name' => 'Updated Preference',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Preference']);

        $this->assertDatabaseHas('cooking_preferences', [
            'id' => $preference->id,
            'name' => 'Updated Preference',
        ]);
    }

    public function test_can_delete_cooking_preference(): void
    {
        $preference = CookingPreference::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Temp Preference',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/cooking-preferences/{$preference->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('cooking_preferences', ['id' => $preference->id]);
    }

    public function test_can_bulk_create_cooking_preferences(): void
    {
        $payload = [
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'preferences' => [
                ['name' => 'Rare'],
                ['name' => 'Medium'],
                ['name' => 'Well Done'],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/cooking-preferences/bulk', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('cooking_preferences', [
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Rare',
        ]);

        $this->assertDatabaseHas('cooking_preferences', [
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Medium',
        ]);

        $this->assertDatabaseHas('cooking_preferences', [
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Well Done',
        ]);
    }
}

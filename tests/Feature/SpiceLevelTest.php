<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\SpiceLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpiceLevelTest extends TestCase
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
            'name' => 'Curries',
            'slug' => 'curries',
        ]);

        $this->menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Chicken Tikka Masala',
            'slug' => 'chicken-tikka-masala',
            'price' => 12.99,
        ]);
    }

    public function test_can_list_spice_levels(): void
    {
        SpiceLevel::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Mild',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/spice-levels');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Mild']);
    }

    public function test_can_filter_spice_levels_by_restaurant_and_menu_item(): void
    {
        SpiceLevel::create([
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Hot',
        ]);

        SpiceLevel::create([
            'name' => 'Extra Spicy',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/spice-levels?restaurant_id={$this->restaurant->id}&menu_item_id={$this->menuItem->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Hot'])
            ->assertJsonMissing(['name' => 'Extra Spicy']);
    }

    public function test_can_create_spice_level(): void
    {
        $payload = [
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Medium',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/spice-levels', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Medium']);

        $this->assertDatabaseHas('spice_levels', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Medium',
        ]);
    }

    public function test_can_show_spice_level(): void
    {
        $level = SpiceLevel::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Extreme Heat',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/spice-levels/{$level->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Extreme Heat']);
    }

    public function test_can_update_spice_level(): void
    {
        $level = SpiceLevel::create([
            'name' => 'Light Spice',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/spice-levels/{$level->id}", [
                'name' => 'Mild Spice',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Mild Spice']);

        $this->assertDatabaseHas('spice_levels', [
            'id' => $level->id,
            'name' => 'Mild Spice',
        ]);
    }

    public function test_can_delete_spice_level(): void
    {
        $level = SpiceLevel::create([
            'name' => 'Temporary Spice',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/spice-levels/{$level->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('spice_levels', ['id' => $level->id]);
    }

    public function test_can_bulk_create_spice_levels(): void
    {
        $payload = [
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'levels' => ['Mild', 'Medium', 'Hot', 'Extra Hot'],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/spice-levels/bulk', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('spice_levels', ['name' => 'Mild']);
        $this->assertDatabaseHas('spice_levels', ['name' => 'Extra Hot']);
    }
}

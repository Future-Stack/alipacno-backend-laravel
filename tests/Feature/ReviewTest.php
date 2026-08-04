<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected Restaurant $restaurant;
    protected MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create([
            'user_type' => 'customer',
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

        $branch = Branch::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Downtown Branch',
            'status' => 'active',
        ]);

        $category = Category::create([
            'branch_id' => $branch->id,
            'name' => 'Pizzas',
            'slug' => 'pizzas',
        ]);

        $this->menuItem = MenuItem::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Margherita Pizza',
            'slug' => 'margherita-pizza',
            'price' => 12.99,
        ]);
    }

    public function test_can_list_reviews(): void
    {
        Review::create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
            'review' => 'Excellent pizza!',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/reviews');

        $response->assertStatus(200)
            ->assertJsonFragment(['review' => 'Excellent pizza!']);
    }

    public function test_can_filter_reviews_by_restaurant_and_rating(): void
    {
        Review::create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
            'review' => 'Great service',
        ]);

        Review::create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'rating' => 2,
            'review' => 'Cold food',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/reviews?restaurant_id={$this->restaurant->id}&rating=5");

        $response->assertStatus(200)
            ->assertJsonFragment(['review' => 'Great service'])
            ->assertJsonMissing(['review' => 'Cold food']);
    }

    public function test_can_create_review_and_update_menu_item_rating(): void
    {
        $payload = [
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'rating' => 4,
            'review' => 'Very tasty crust!',
        ];

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/reviews', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['rating' => 4]);

        $this->assertDatabaseHas('reviews', [
            'restaurant_id' => $this->restaurant->id,
            'rating' => 4,
        ]);

        $this->menuItem->refresh();
        $this->assertEquals(4.00, $this->menuItem->rating);
        $this->assertEquals(1, $this->menuItem->review_count);
    }

    public function test_can_show_review(): void
    {
        $review = Review::create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
            'review' => 'Top notch!',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['review' => 'Top notch!']);
    }

    public function test_can_update_review(): void
    {
        $review = Review::create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'menu_item_id' => $this->menuItem->id,
            'rating' => 3,
            'review' => 'Average pizza',
        ]);

        $response = $this->actingAs($this->customer)
            ->putJson("/api/v1/reviews/{$review->id}", [
                'rating' => 5,
                'review' => 'Actually it was amazing!',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['rating' => 5]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 5,
        ]);

        $this->menuItem->refresh();
        $this->assertEquals(5.00, $this->menuItem->rating);
    }

    public function test_can_delete_review(): void
    {
        $review = Review::create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'rating' => 4,
            'review' => 'Bad review to delete',
        ]);

        $response = $this->actingAs($this->customer)
            ->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_can_get_review_summary(): void
    {
        Review::create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
            'review' => 'Amazing',
        ]);

        Review::create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant->id,
            'rating' => 4,
            'review' => 'Good',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/reviews/summary?restaurant_id={$this->restaurant->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['total_reviews' => 2, 'average_rating' => 4.5]);
    }
}

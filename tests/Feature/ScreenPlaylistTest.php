<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\ScreenPlaylist;
use App\Models\SignageContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenPlaylistTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Branch $branch;
    protected SignageContent $content1;
    protected SignageContent $content2;

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

        $this->content1 = SignageContent::create([
            'title' => 'Summer Promo Video',
            'content_type' => 'video',
            'file' => 'https://example.com/video1.mp4',
            'duration' => 30,
        ]);

        $this->content2 = SignageContent::create([
            'title' => 'Menu Board Image',
            'content_type' => 'image',
            'file' => 'https://example.com/image1.jpg',
            'duration' => 15,
        ]);
    }

    public function test_can_list_screen_playlists(): void
    {
        ScreenPlaylist::create([
            'title' => 'Morning Playlist',
            'description' => 'Displays during breakfast hours',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/screen-playlists');

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Morning Playlist']);
    }

    public function test_can_filter_screen_playlists_by_search(): void
    {
        ScreenPlaylist::create([
            'title' => 'Breakfast Special',
            'description' => 'Morning deals',
            'created_by' => $this->adminUser->id,
        ]);

        ScreenPlaylist::create([
            'title' => 'Evening Cocktails',
            'description' => 'Happy hour deals',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/screen-playlists?search=Breakfast');

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Breakfast Special'])
            ->assertJsonMissing(['title' => 'Evening Cocktails']);
    }

    public function test_can_create_screen_playlist_with_items(): void
    {
        $payload = [
            'title' => 'Lunch Rotation',
            'description' => 'Midday signage slideshow',
            'items' => [
                ['signage_content_id' => $this->content1->id, 'sort_order' => 1],
                ['signage_content_id' => $this->content2->id, 'sort_order' => 2],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/screen-playlists', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['title' => 'Lunch Rotation']);

        $this->assertDatabaseHas('screen_playlists', ['title' => 'Lunch Rotation']);
        $this->assertDatabaseHas('screen_playlist_items', ['signage_content_id' => $this->content1->id]);
    }

    public function test_can_show_screen_playlist(): void
    {
        $playlist = ScreenPlaylist::create([
            'title' => 'Weekend Deals',
            'description' => 'Special offers',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/screen-playlists/{$playlist->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Weekend Deals']);
    }

    public function test_can_update_screen_playlist(): void
    {
        $playlist = ScreenPlaylist::create([
            'title' => 'Old Title',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/screen-playlists/{$playlist->id}", [
                'title' => 'Updated Title',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Updated Title']);

        $this->assertDatabaseHas('screen_playlists', [
            'id' => $playlist->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_can_delete_screen_playlist(): void
    {
        $playlist = ScreenPlaylist::create([
            'title' => 'Temporary Playlist',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/screen-playlists/{$playlist->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('screen_playlists', ['id' => $playlist->id]);
    }

    public function test_can_sync_playlist_items(): void
    {
        $playlist = ScreenPlaylist::create([
            'title' => 'Sync Test Playlist',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/screen-playlists/{$playlist->id}/sync-items", [
                'items' => [
                    ['signage_content_id' => $this->content1->id, 'sort_order' => 1],
                    ['signage_content_id' => $this->content2->id, 'sort_order' => 2],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('screen_playlist_items', [
            'playlist_id' => $playlist->id,
            'signage_content_id' => $this->content1->id,
        ]);
    }

    public function test_can_add_item_to_playlist(): void
    {
        $playlist = ScreenPlaylist::create([
            'title' => 'Add Item Playlist',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/screen-playlists/{$playlist->id}/add-item", [
                'signage_content_id' => $this->content1->id,
                'sort_order' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('screen_playlist_items', [
            'playlist_id' => $playlist->id,
            'signage_content_id' => $this->content1->id,
        ]);
    }
}

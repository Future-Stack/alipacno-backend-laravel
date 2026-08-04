<?php

namespace Tests\Feature;

use App\Models\SignageContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignageContentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'user_type' => 'super_admin',
        ]);
    }

    public function test_can_list_signage_contents(): void
    {
        SignageContent::create([
            'title' => 'Welcome Banner',
            'content_type' => 'image',
            'file' => 'https://example.com/banner.png',
            'duration' => 15,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/signage-contents');

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Welcome Banner']);
    }

    public function test_can_filter_signage_contents_by_type_and_status(): void
    {
        SignageContent::create([
            'title' => 'Promo Video',
            'content_type' => 'video',
            'file' => 'https://example.com/promo.mp4',
            'duration' => 30,
            'status' => 'active',
        ]);

        SignageContent::create([
            'title' => 'Expired Image',
            'content_type' => 'image',
            'file' => 'https://example.com/old.jpg',
            'duration' => 10,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/signage-contents?content_type=video&status=active');

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Promo Video'])
            ->assertJsonMissing(['title' => 'Expired Image']);
    }

    public function test_can_create_signage_content(): void
    {
        $payload = [
            'title' => 'Lunch Special Advert',
            'content_type' => 'image',
            'file' => 'https://example.com/lunch.png',
            'duration' => 20,
            'status' => 'active',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/signage-contents', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['title' => 'Lunch Special Advert']);

        $this->assertDatabaseHas('signage_contents', [
            'title' => 'Lunch Special Advert',
            'duration' => 20,
        ]);
    }

    public function test_can_show_signage_content(): void
    {
        $content = SignageContent::create([
            'title' => 'Cocktail Hour Video',
            'content_type' => 'video',
            'file' => 'https://example.com/cocktail.mp4',
            'duration' => 45,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/signage-contents/{$content->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Cocktail Hour Video']);
    }

    public function test_can_update_signage_content(): void
    {
        $content = SignageContent::create([
            'title' => 'Old Title',
            'content_type' => 'image',
            'file' => 'https://example.com/image.jpg',
            'duration' => 10,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/signage-contents/{$content->id}", [
                'title' => 'Updated Title',
                'duration' => 15,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Updated Title']);

        $this->assertDatabaseHas('signage_contents', [
            'id' => $content->id,
            'title' => 'Updated Title',
            'duration' => 15,
        ]);
    }

    public function test_can_delete_signage_content(): void
    {
        $content = SignageContent::create([
            'title' => 'Temp Content',
            'content_type' => 'image',
            'file' => 'https://example.com/temp.jpg',
            'duration' => 5,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/signage-contents/{$content->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('signage_contents', ['id' => $content->id]);
    }

    public function test_can_toggle_signage_content_status(): void
    {
        $content = SignageContent::create([
            'title' => 'Toggle Content',
            'content_type' => 'image',
            'file' => 'https://example.com/toggle.png',
            'duration' => 10,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/signage-contents/{$content->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'inactive']);

        $this->assertDatabaseHas('signage_contents', [
            'id' => $content->id,
            'status' => 'inactive',
        ]);
    }
}

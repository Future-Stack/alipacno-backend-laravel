<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DigitalScreen;
use App\Models\Restaurant;
use App\Models\ScreenPlaylist;
use App\Models\ScreenSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected DigitalScreen $screen;
    protected ScreenPlaylist $playlist;

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

        $branch = Branch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Downtown Branch',
            'status' => 'active',
        ]);

        $this->screen = DigitalScreen::create([
            'branch_id' => $branch->id,
            'screen_name' => 'Lobby TV 1',
            'device_uuid' => 'uuid-001',
            'resolution' => '1920x1080',
            'status' => 'online',
        ]);

        $this->playlist = ScreenPlaylist::create([
            'title' => 'Morning Specials',
            'description' => 'Breakfast playlist',
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_can_list_screen_schedules(): void
    {
        ScreenSchedule::create([
            'screen_id' => $this->screen->id,
            'playlist_id' => $this->playlist->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'start_time' => '08:00',
            'end_time' => '11:30',
            'repeat_type' => 'daily',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/screen-schedules');

        $response->assertStatus(200)
            ->assertJsonFragment(['repeat_type' => 'daily']);
    }

    public function test_can_filter_screen_schedules_by_screen_and_status(): void
    {
        ScreenSchedule::create([
            'screen_id' => $this->screen->id,
            'playlist_id' => $this->playlist->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'start_time' => '08:00',
            'end_time' => '11:30',
            'repeat_type' => 'daily',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/screen-schedules?screen_id={$this->screen->id}&status=active");

        $response->assertStatus(200)
            ->assertJsonFragment(['repeat_type' => 'daily']);
    }

    public function test_can_create_screen_schedule(): void
    {
        $payload = [
            'screen_id' => $this->screen->id,
            'playlist_id' => $this->playlist->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'start_time' => '12:00',
            'end_time' => '15:00',
            'repeat_type' => 'weekly',
            'priority' => 2,
            'status' => 'active',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/screen-schedules', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['repeat_type' => 'weekly']);

        $this->assertDatabaseHas('screen_schedules', [
            'screen_id' => $this->screen->id,
            'playlist_id' => $this->playlist->id,
            'repeat_type' => 'weekly',
        ]);
    }

    public function test_can_show_screen_schedule(): void
    {
        $schedule = ScreenSchedule::create([
            'screen_id' => $this->screen->id,
            'playlist_id' => $this->playlist->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'start_time' => '08:00',
            'end_time' => '11:30',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/screen-schedules/{$schedule->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['start_time' => '08:00']);
    }

    public function test_can_update_screen_schedule(): void
    {
        $schedule = ScreenSchedule::create([
            'screen_id' => $this->screen->id,
            'playlist_id' => $this->playlist->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'start_time' => '08:00',
            'end_time' => '11:30',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/screen-schedules/{$schedule->id}", [
                'end_time' => '12:00',
                'priority' => 5,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['end_time' => '12:00']);

        $this->assertDatabaseHas('screen_schedules', [
            'id' => $schedule->id,
            'end_time' => '12:00',
            'priority' => 5,
        ]);
    }

    public function test_can_delete_screen_schedule(): void
    {
        $schedule = ScreenSchedule::create([
            'screen_id' => $this->screen->id,
            'playlist_id' => $this->playlist->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'start_time' => '08:00',
            'end_time' => '11:30',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/screen-schedules/{$schedule->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('screen_schedules', ['id' => $schedule->id]);
    }

    public function test_can_toggle_screen_schedule_status(): void
    {
        $schedule = ScreenSchedule::create([
            'screen_id' => $this->screen->id,
            'playlist_id' => $this->playlist->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'start_time' => '08:00',
            'end_time' => '11:30',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/screen-schedules/{$schedule->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'inactive']);

        $this->assertDatabaseHas('screen_schedules', [
            'id' => $schedule->id,
            'status' => 'inactive',
        ]);
    }
}

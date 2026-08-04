<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Staff $staffMember;

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

        $this->staffMember = Staff::create([
            'branch_id' => $branch->id,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '1234567890',
            'status' => 'active',
        ]);
    }

    public function test_can_list_staff_attendances(): void
    {
        StaffAttendance::create([
            'staff_id' => $this->staffMember->id,
            'clock_in' => '2026-08-01 08:00:00',
            'clock_out' => '2026-08-01 16:00:00',
            'total_hours' => 8.0,
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/staff-attendance');

        $response->assertStatus(200)
            ->assertJsonFragment(['total_hours' => 8.0]);
    }

    public function test_can_filter_staff_attendances_by_staff_id_and_status(): void
    {
        StaffAttendance::create([
            'staff_id' => $this->staffMember->id,
            'clock_in' => '2026-08-01 08:00:00',
            'clock_out' => '2026-08-01 16:00:00',
            'total_hours' => 8.0,
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/staff-attendance?staff_id={$this->staffMember->id}&status=present");

        $response->assertStatus(200)
            ->assertJsonFragment(['total_hours' => 8.0]);
    }

    public function test_can_create_staff_attendance(): void
    {
        $payload = [
            'staff_id' => $this->staffMember->id,
            'clock_in' => '2026-08-02 09:00:00',
            'clock_out' => '2026-08-02 17:30:00',
            'status' => 'present',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/staff-attendance', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['total_hours' => 8.5]);

        $this->assertDatabaseHas('staff_attendance', [
            'staff_id' => $this->staffMember->id,
            'total_hours' => 8.5,
        ]);
    }

    public function test_can_show_staff_attendance(): void
    {
        $attendance = StaffAttendance::create([
            'staff_id' => $this->staffMember->id,
            'clock_in' => '2026-08-01 08:00:00',
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/staff-attendance/{$attendance->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['staff_id' => $this->staffMember->id]);
    }

    public function test_can_update_staff_attendance(): void
    {
        $attendance = StaffAttendance::create([
            'staff_id' => $this->staffMember->id,
            'clock_in' => '2026-08-01 08:00:00',
            'status' => 'late',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/staff-attendance/{$attendance->id}", [
                'clock_out' => '2026-08-01 16:00:00',
                'status' => 'present',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'present', 'total_hours' => 8.0]);

        $this->assertDatabaseHas('staff_attendance', [
            'id' => $attendance->id,
            'total_hours' => 8.0,
            'status' => 'present',
        ]);
    }

    public function test_can_delete_staff_attendance(): void
    {
        $attendance = StaffAttendance::create([
            'staff_id' => $this->staffMember->id,
            'clock_in' => '2026-08-01 08:00:00',
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/staff-attendance/{$attendance->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('staff_attendance', ['id' => $attendance->id]);
    }

    public function test_staff_can_clock_in(): void
    {
        $payload = [
            'staff_id' => $this->staffMember->id,
            'status' => 'present',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/staff-attendance/clock-in', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('staff_attendance', [
            'staff_id' => $this->staffMember->id,
            'clock_out' => null,
        ]);
    }

    public function test_staff_can_clock_out(): void
    {
        $attendance = StaffAttendance::create([
            'staff_id' => $this->staffMember->id,
            'clock_in' => now()->subHours(8),
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/staff-attendance/{$attendance->id}/clock-out");

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('staff_attendance', [
            'id' => $attendance->id,
            'total_hours' => 8.0,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffPayout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffEarningsTest extends TestCase
{
    use RefreshDatabase;

    protected User $staffUser;
    protected User $adminUser;
    protected Staff $staffMember;
    protected Branch $branch;

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

        $this->staffUser = User::factory()->create([
            'name' => 'John Waiter',
            'email' => 'waiter@example.com',
            'user_type' => 'staff',
        ]);

        $this->staffMember = Staff::create([
            'user_id' => $this->staffUser->id,
            'employee_id' => 'EMP-001',
            'branch_id' => $this->branch->id,
            'name' => 'John Waiter',
            'email' => 'waiter@example.com',
            'phone' => '1234567890',
            'salary' => 10.00,
            'commission' => 5.00,
            'status' => 'active',
        ]);
    }

    public function test_staff_can_view_their_weekly_earnings_breakdown(): void
    {
        $currentWeekStart = Carbon::now()->startOfWeek();
        $previousWeekStart = Carbon::now()->subWeek()->startOfWeek();

        // 8 hours worked in the current week (8 x £10 = £80)
        StaffAttendance::create([
            'staff_id' => $this->staffMember->id,
            'clock_in' => $currentWeekStart->copy()->addHours(8),
            'clock_out' => $currentWeekStart->copy()->addHours(16),
            'total_hours' => 8.0,
            'status' => 'present',
        ]);

        // 12 hours worked in the previous week (12 x £10 = £120)
        StaffAttendance::create([
            'staff_id' => $this->staffMember->id,
            'clock_in' => $previousWeekStart->copy()->addHours(8),
            'clock_out' => $previousWeekStart->copy()->addHours(20),
            'total_hours' => 12.0,
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->staffUser)
            ->getJson('/api/v1/staff/earnings');

        $response->assertStatus(200)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.staff_id', $this->staffMember->id)
            ->assertJsonPath('data.stripe_onboarding_completed', false)
            ->assertJsonPath('data.current_week.hours_worked', 8.0)
            ->assertJsonPath('data.current_week.hourly_rate', 10.0)
            ->assertJsonPath('data.current_week.gross_earnings', 80.0)
            ->assertJsonPath('data.current_week.net_payout', 80.0)
            ->assertJsonPath('data.previous_week_lagged.hours_worked', 12.0)
            ->assertJsonPath('data.previous_week_lagged.gross_earnings', 120.0)
            ->assertJsonPath('data.previous_week_lagged.net_payout', 120.0);
    }

    public function test_staff_earnings_includes_previous_week_payout_history(): void
    {
        $previousWeek = Carbon::now()->subWeek();
        $year = $previousWeek->year;
        $weekNumber = $previousWeek->weekOfYear;

        StaffPayout::create([
            'staff_id' => $this->staffMember->id,
            'year' => $year,
            'week_number' => $weekNumber,
            'start_date' => $previousWeek->copy()->startOfWeek()->toDateString(),
            'end_date' => $previousWeek->copy()->endOfWeek()->toDateString(),
            'hours_worked' => 12.0,
            'hourly_rate' => 10.0,
            'gross_earnings' => 120.0,
            'net_payout' => 120.0,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->staffUser)
            ->getJson('/api/v1/staff/earnings');

        $response->assertStatus(200)
            ->assertJsonPath('data.payout_history.0.staff_id', $this->staffMember->id)
            ->assertJsonPath('data.payout_history.0.year', $year)
            ->assertJsonPath('data.payout_history.0.week_number', $weekNumber)
            ->assertJsonPath('data.payout_history.0.net_payout', 120.0)
            ->assertJsonCount(1, 'data.payout_history');
    }

    public function test_admin_can_view_staff_earnings_by_staff_id(): void
    {
        StaffAttendance::create([
            'staff_id' => $this->staffMember->id,
            'clock_in' => Carbon::now()->startOfWeek()->addHours(8),
            'clock_out' => Carbon::now()->startOfWeek()->addHours(16),
            'total_hours' => 8.0,
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/staff/earnings?staff_id=' . $this->staffMember->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.staff_id', $this->staffMember->id)
            ->assertJsonPath('data.current_week.hours_worked', 8.0);
    }

    public function test_user_without_staff_profile_gets_404(): void
    {
        $noProfileUser = User::factory()->create([
            'name' => 'No Profile',
            'email' => 'noprofile@example.com',
            'user_type' => 'staff',
        ]);

        $response = $this->actingAs($noProfileUser)
            ->getJson('/api/v1/staff/earnings');

        $response->assertStatus(404)
            ->assertJsonPath('status', 404);
    }
}
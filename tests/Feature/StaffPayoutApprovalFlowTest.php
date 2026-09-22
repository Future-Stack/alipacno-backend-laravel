<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffPayout;
use App\Models\User;
use App\Services\StaffAttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffPayoutApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $staffUser;
    protected Staff $staffMember;
    protected Staff $staffMemberTwo;
    protected Branch $branch;
    protected string $weekStart;
    protected string $weekEnd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['user_type' => 'super_admin']);
        $this->staffUser = User::factory()->create([
            'name' => 'John Waiter',
            'email' => 'waiter@example.com',
            'user_type' => 'staff',
        ]);
        Sanctum::actingAs($this->adminUser);

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

        $this->staffMember = Staff::create([
            'user_id' => $this->staffUser->id,
            'employee_id' => 'EMP-001',
            'branch_id' => $this->branch->id,
            'name' => 'John Waiter',
            'email' => 'waiter@example.com',
            'phone' => '1234567890',
            'salary' => 10.00,
            'status' => 'active',
        ]);

        $this->staffMemberTwo = Staff::create([
            'employee_id' => 'EMP-002',
            'branch_id' => $this->branch->id,
            'name' => 'Jane Cook',
            'email' => 'cook@example.com',
            'phone' => '0987654321',
            'salary' => 12.00,
            'status' => 'active',
        ]);

        $this->weekStart = Carbon::now()->startOfWeek()->toDateString();
        $this->weekEnd = Carbon::now()->endOfWeek()->toDateString();
    }

    protected function approvePayload(array $overrides = []): array
    {
        return array_merge([
            'start_date' => $this->weekStart,
            'end_date' => $this->weekEnd,
            'manual_payment' => true,
        ], $overrides);
    }

    protected function createTimecard(Staff $staff, float $hours, float $rate): StaffAttendance
    {
        return StaffAttendance::create([
            'staff_id' => $staff->id,
            'clock_in' => Carbon::parse($this->weekStart)->addHours(8),
            'clock_out' => Carbon::parse($this->weekStart)->addHours(8 + $hours),
            'total_hours' => $hours,
            'hourly_rate' => $rate,
            'shift_earnings' => round($hours * $rate, 2),
            'status' => 'present',
        ]);
    }

    public function test_clock_out_accrues_shift_earnings(): void
    {
        $attendance = StaffAttendance::create([
            'staff_id' => $this->staffMember->id,
            'clock_in' => Carbon::parse($this->weekStart)->addHours(8),
            'clock_out' => Carbon::parse($this->weekStart)->addHours(16),
            'total_hours' => 8.0,
            'status' => 'present',
        ]);

        app(StaffAttendanceService::class)->applyShiftEarnings($attendance);

        $this->assertEquals(10.0, (float) $attendance->fresh()->hourly_rate);
        $this->assertEquals(80.0, (float) $attendance->fresh()->shift_earnings);
    }

    public function test_single_approve_pays_and_links_timecards(): void
    {
        $this->createTimecard($this->staffMember, 8.0, 10.00);

        $response = $this->postJson('/api/v1/staff-payouts/approve', $this->approvePayload([
            'staff_id' => $this->staffMember->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.staff_id', $this->staffMember->id)
            ->assertJsonPath('data.status', 'paid');
        $this->assertEquals(8.0, $response->json('data.hours_worked'));
        $this->assertEquals(80.0, $response->json('data.gross_earnings'));
        $this->assertNotNull($response->json('data.approved_at'));

        $this->assertDatabaseHas('staff_attendance', [
            'staff_id' => $this->staffMember->id,
            'payout_id' => $response->json('data.id'),
        ]);
    }

    public function test_approve_skips_when_no_unpaid_timecards(): void
    {
        $this->createTimecard($this->staffMember, 8.0, 10.00);

        $this->postJson('/api/v1/staff-payouts/approve', $this->approvePayload([
            'staff_id' => $this->staffMember->id,
        ]))->assertOk();

        $this->postJson('/api/v1/staff-payouts/approve', $this->approvePayload([
            'staff_id' => $this->staffMember->id,
        ]))->assertStatus(422)
            ->assertJsonFragment(['message' => 'No payable (unpaid) timecards found for this staff member in the given range.']);
    }

    public function test_paid_timecard_cannot_be_edited(): void
    {
        $attendance = $this->createTimecard($this->staffMember, 8.0, 10.00);

        $this->postJson('/api/v1/staff-payouts/approve', $this->approvePayload([
            'staff_id' => $this->staffMember->id,
        ]))->assertOk();

        $this->putJson("/api/v1/staff-attendance/{$attendance->id}", [
            'clock_out' => Carbon::parse($this->weekStart)->addHours(10)->toDateTimeString(),
            'notes' => 'attempted correction',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'This timecard is already included in a paid payout and cannot be edited.']);
    }

    public function test_batch_approve_processes_all_staff_in_branch(): void
    {
        $this->createTimecard($this->staffMember, 8.0, 10.00);
        $this->createTimecard($this->staffMemberTwo, 5.0, 12.00);

        $response = $this->postJson('/api/v1/staff-payouts/approve-batch', $this->approvePayload([
            'branch_id' => $this->branch->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.summary.paid_count', 2);
        $this->assertEquals(140.0, $response->json('data.summary.total_paid'));

        $this->assertDatabaseHas('staff_payouts', [
            'staff_id' => $this->staffMember->id,
            'branch_id' => $this->branch->id,
            'gross_earnings' => 80.0,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('staff_payouts', [
            'staff_id' => $this->staffMemberTwo->id,
            'gross_earnings' => 60.0,
            'status' => 'paid',
        ]);
    }

    public function test_batch_approve_does_not_double_pay_already_paid_shifts(): void
    {
        $this->createTimecard($this->staffMember, 8.0, 10.00);

        $this->postJson('/api/v1/staff-payouts/approve', $this->approvePayload([
            'staff_id' => $this->staffMember->id,
        ]))->assertOk();

        $this->postJson('/api/v1/staff-payouts/approve-batch', $this->approvePayload([
            'branch_id' => $this->branch->id,
        ]))->assertOk()
            ->assertJsonPath('data.summary.paid_count', 0);

        $this->assertSame(1, StaffPayout::count());
    }

    public function test_payroll_review_lists_unpaid_and_missing_clock_outs(): void
    {
        $this->createTimecard($this->staffMember, 8.0, 10.00);

        StaffAttendance::create([
            'staff_id' => $this->staffMemberTwo->id,
            'clock_in' => Carbon::parse($this->weekStart)->addHours(9),
            'status' => 'present',
        ]);

        $response = $this->getJson('/api/v1/staff-payouts/payroll-review?branch_id=' . $this->branch->id);

        $response->assertOk();
        $this->assertEquals($this->branch->id, $response->json('data.branch_id'));

        $payable = collect($response->json('data.staff_payable'))->firstWhere('staff_id', $this->staffMember->id);
        $this->assertNotNull($payable);
        $this->assertEquals(8.0, $payable['hours_worked']);
        $this->assertEquals(80.0, $payable['gross_earnings']);
        $this->assertEquals(80.0, $response->json('data.total_unpaid_gross'));

        $missing = collect($response->json('data.staff_payable'))->firstWhere('staff_id', $this->staffMemberTwo->id);
        $this->assertNotNull($missing);
        $this->assertEquals(1, $missing['missing_clock_outs']);
        $this->assertEquals(0, $missing['shift_count']);
    }

    public function test_salary_history_returns_paid_ledger(): void
    {
        $this->createTimecard($this->staffMember, 8.0, 10.00);

        $this->postJson('/api/v1/staff-payouts/approve', $this->approvePayload([
            'staff_id' => $this->staffMember->id,
        ]))->assertOk();

        $response = $this->actingAs($this->staffUser)
            ->getJson('/api/v1/staff/salary-history');

        $response->assertOk()
            ->assertJsonPath('data.staff_id', $this->staffMember->id)
            ->assertJsonPath('data.history.total', 1);
        $this->assertEquals(80.0, $response->json('data.totals.net_paid'));
    }
}
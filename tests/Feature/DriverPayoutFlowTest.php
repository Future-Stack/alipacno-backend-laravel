<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Delivery;
use App\Models\DeliveryFeeTier;
use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\DriverShift;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\DriverPayoutService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverPayoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $driverUser;
    protected Driver $driver;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'user_type' => 'super_admin',
        ]);

        $restaurant = Restaurant::create([
            'name' => 'Alipacino Pizza',
            'slug' => 'alipacino-pizza',
            'phone' => '1234567890',
            'email' => 'restaurant@example.com',
            'address' => '123 Main St',
            'postcode' => '12345',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Central Branch',
            'status' => 'active',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
        ]);

        $this->driverUser = User::factory()->create([
            'name' => 'John Driver',
            'email' => 'driver@example.com',
            'user_type' => 'driver',
        ]);

        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id,
            'branch_id' => $this->branch->id,
            'name' => 'John Driver',
            'phone' => '07123456789',
            'vehicle_type' => 'Motorcycle',
            'status' => 'offline',
            'is_online' => false,
            'hourly_rate' => 10.00, // £10/hr
        ]);
    }

    public function test_distance_based_fee_tier_calculations(): void
    {
        // 0-3 miles tier = £1.00 per drop
        $this->assertEquals(1.00, DeliveryFeeTier::calculateFeeForDistance(2.0));

        // 3-5 miles tier = £2.00 per drop
        $this->assertEquals(2.00, DeliveryFeeTier::calculateFeeForDistance(4.0));

        // 5+ miles tier = £3.00 per drop
        $this->assertEquals(3.00, DeliveryFeeTier::calculateFeeForDistance(7.0));
    }

    public function test_driver_shift_clock_in_and_clock_out_duration(): void
    {
        // Start shift
        $shift = DriverShift::create([
            'driver_id' => $this->driver->id,
            'branch_id' => $this->branch->id,
            'clock_in_at' => now()->subHours(8),
            'status' => 'active',
        ]);

        $this->driver->update(['is_online' => true, 'status' => 'available']);

        $this->assertTrue($this->driver->fresh()->is_online);

        // End shift
        $clockOutTime = now();
        $totalHours = round($shift->clock_in_at->diffInMinutes($clockOutTime) / 60, 2);

        $shift->update([
            'clock_out_at' => $clockOutTime,
            'total_hours' => $totalHours,
            'status' => 'completed',
        ]);

        $this->assertEquals(8.00, $shift->fresh()->total_hours);
        $this->assertEquals('completed', $shift->fresh()->status);
    }

    public function test_net_weekly_payout_calculation_formula(): void
    {
        $previousWeek = Carbon::now()->subWeek();
        $startDate = $previousWeek->copy()->startOfWeek();
        $endDate = $previousWeek->copy()->endOfWeek();

        // 1. Create a 10-hour shift for previous week (10 hours x £10/hr = £100)
        DriverShift::create([
            'driver_id' => $this->driver->id,
            'branch_id' => $this->branch->id,
            'clock_in_at' => $startDate->copy()->addHours(8),
            'clock_out_at' => $startDate->copy()->addHours(18),
            'total_hours' => 10.00,
            'completed_drops' => 2,
            'total_distance_miles' => 5.0,
            'status' => 'completed',
        ]);

        // 2. Create order 1 (Online paid): Drop fee £1.00 + Tip £3.00
        $order1 = Order::create([
            'order_number' => 'ORD-1001',
            'restaurant_id' => $this->branch->restaurant_id,
            'branch_id' => $this->branch->id,
            'order_type' => 'delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'subtotal' => 20.00,
            'total' => 23.00,
            'rider_tip' => 3.00,
        ]);

        Delivery::create([
            'order_id' => $order1->id,
            'driver_id' => $this->driver->id,
            'delivery_status' => 'delivered',
            'delivered_time' => $startDate->copy()->addHours(10),
            'distance_miles' => 2.0, // 0-3 mi tier = £1.00
            'driver_fee' => 1.00,
            'is_cod' => false,
            'cash_collected' => 0.00,
        ]);

        // 3. Create order 2 (COD Cash): Drop fee £2.00, Customer paid £25 cash at door
        $order2 = Order::create([
            'order_number' => 'ORD-1002',
            'restaurant_id' => $this->branch->restaurant_id,
            'branch_id' => $this->branch->id,
            'order_type' => 'delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'subtotal' => 25.00,
            'total' => 25.00,
            'rider_tip' => 0.00,
        ]);

        Delivery::create([
            'order_id' => $order2->id,
            'driver_id' => $this->driver->id,
            'delivery_status' => 'delivered',
            'delivered_time' => $startDate->copy()->addHours(12),
            'distance_miles' => 4.0, // 3-5 mi tier = £2.00
            'driver_fee' => 2.00,
            'is_cod' => true,
            'cash_collected' => 25.00, // Cash kept at door
        ]);

        // Formula Expectations:
        // Hours Worked = 10 hrs * £10 = £100.00
        // Delivery Fees = £1.00 + £2.00 = £3.00
        // Tips = £3.00
        // Gross Earnings = £100 + £3 + £3 = £106.00
        // Cash Kept (COD) = £25.00
        // Net Weekly Payout = £106.00 - £25.00 = £81.00

        $payoutService = new DriverPayoutService();
        $calc = $payoutService->calculateWeeklyEarnings($this->driver, $startDate, $endDate);

        $this->assertEquals(10.00, $calc['hours_worked']);
        $this->assertEquals(100.00, $calc['hourly_earnings']);
        $this->assertEquals(3.00, $calc['delivery_fees']);
        $this->assertEquals(3.00, $calc['tips']);
        $this->assertEquals(106.00, $calc['gross_earnings']);
        $this->assertEquals(25.00, $calc['cash_collected']);
        $this->assertEquals(81.00, $calc['net_payout']);
    }

    public function test_driver_payout_generation_and_storage(): void
    {
        $previousWeek = Carbon::now()->subWeek();
        $year = $previousWeek->year;
        $weekNumber = $previousWeek->weekOfYear;

        $payoutService = new DriverPayoutService();
        $payout = $payoutService->generateWeeklyPayout($this->driver, $year, $weekNumber);

        $this->assertInstanceOf(DriverPayout::class, $payout);
        $this->assertEquals('pending', $payout->status);
        $this->assertDatabaseHas('driver_payouts', [
            'driver_id' => $this->driver->id,
            'year' => $year,
            'week_number' => $weekNumber,
        ]);
    }
}

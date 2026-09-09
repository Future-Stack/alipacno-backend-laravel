<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffPayout;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StaffPayoutService
{
    /**
     * Calculate the hourly salary breakdown for a staff member over a given date range.
     */
    public function calculateEarnings(Staff $staff, Carbon $startDate, Carbon $endDate): array
    {
        $attendances = StaffAttendance::where('staff_id', $staff->id)
            ->whereBetween('clock_in', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereNotNull('clock_out')
            ->get();

        $hoursWorked = (float) $attendances->sum('total_hours');
        $hourlyRate = (float) ($staff->salary ?? 0.00);
        $grossEarnings = round($hoursWorked * $hourlyRate, 2);
        $netPayout = round(max(0, $grossEarnings), 2);

        return [
            'staff_id' => $staff->id,
            'staff_name' => $staff->name,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'hours_worked' => round($hoursWorked, 2),
            'hourly_rate' => $hourlyRate,
            'gross_earnings' => $grossEarnings,
            'net_payout' => $netPayout,
        ];
    }

    /**
     * Generate or update a StaffPayout record for a specific staff member and ISO week.
     */
    public function generateWeeklyPayout(Staff $staff, int $year, int $weekNumber): StaffPayout
    {
        $date = Carbon::now()->setISODate($year, $weekNumber);
        $startDate = $date->copy()->startOfWeek();
        $endDate = $date->copy()->endOfWeek();

        $calc = $this->calculateEarnings($staff, $startDate, $endDate);

        return StaffPayout::updateOrCreate(
            [
                'staff_id' => $staff->id,
                'year' => $year,
                'week_number' => $weekNumber,
            ],
            [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'hours_worked' => $calc['hours_worked'],
                'hourly_rate' => $calc['hourly_rate'],
                'gross_earnings' => $calc['gross_earnings'],
                'net_payout' => $calc['net_payout'],
                'status' => 'pending',
            ]
        );
    }

    /**
     * Process weekly payouts for ALL staff for the previous week (Week N - 1 week lag).
     */
    public function processLaggedWeeklyPayouts(): array
    {
        $previousWeek = Carbon::now()->subWeek();
        $year = $previousWeek->year;
        $weekNumber = $previousWeek->weekOfYear;

        $staffMembers = Staff::where('status', '!=', 'off_duty')->orWhereHas('attendances')->get();
        $processed = [];

        foreach ($staffMembers as $staff) {
            $payout = $this->generateWeeklyPayout($staff, $year, $weekNumber);
            if ($payout->net_payout > 0 && $payout->status === 'pending') {
                $this->executeStripeTransfer($payout);
            }
            $processed[] = $payout;
        }

        return $processed;
    }

    /**
     * Execute a Stripe Connect transfer to a staff member if Stripe is configured.
     * Idempotent: skips transfers that have already been paid.
     */
    public function executeStripeTransfer(StaffPayout $payout): bool
    {
        $staff = $payout->staff;
        if (!$staff || !$staff->stripe_account_id || !$staff->stripe_onboarding_completed) {
            Log::info("Staff payout {$payout->id} pending manual payment or Stripe setup for staff {$staff->name}.");
            return false;
        }

        if ($payout->stripe_transfer_id || $payout->status === 'paid') {
            Log::info("Staff payout {$payout->id} already paid; skipping Stripe transfer to avoid duplicate payment.");
            return true;
        }

        $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');
        if (!$stripeSecret) {
            Log::warning("Stripe secret key not configured for staff payout {$payout->id}.");
            return false;
        }

        try {
            $stripe = new StripeClient($stripeSecret);

            $amountInCents = (int) round($payout->net_payout * 100);
            if ($amountInCents <= 0) {
                Log::warning("Staff payout {$payout->id} has zero net payout; skipping transfer.");
                return false;
            }

            $transfer = $stripe->transfers->create([
                'amount' => $amountInCents,
                'currency' => 'gbp',
                'destination' => $staff->stripe_account_id,
                'description' => "Staff Salary Payout - Week {$payout->week_number}, {$payout->year}",
            ]);

            $payout->update([
                'status' => 'paid',
                'stripe_transfer_id' => $transfer->id,
                'paid_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to execute Stripe Connect transfer for staff payout {$payout->id}: " . $e->getMessage());
            $payout->update(['status' => 'failed', 'notes' => $e->getMessage()]);
            return false;
        }
    }
}

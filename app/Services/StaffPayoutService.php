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
     * Prefers the per-shift accrued earnings snapshot when available.
     */
    public function calculateEarnings(Staff $staff, Carbon $startDate, Carbon $endDate): array
    {
        $attendances = StaffAttendance::with('staff')
            ->where('staff_id', $staff->id)
            ->whereBetween('clock_in', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereNotNull('clock_out')
            ->whereNotNull('total_hours')
            ->get();

        $hoursWorked = (float) $attendances->sum('total_hours');

        $gross = (float) $attendances->sum(function (StaffAttendance $attendance) {
            if ($attendance->shift_earnings !== null) {
                return (float) $attendance->shift_earnings;
            }

            $rate = $attendance->hourly_rate ?? (float) ($attendance->staff?->salary ?? 0.00);

            return (float) $attendance->total_hours * $rate;
        });

        $hourlyRate = $hoursWorked > 0 ? round($gross / $hoursWorked, 2) : (float) ($staff->salary ?? 0.00);

        return [
            'staff_id' => $staff->id,
            'staff_name' => $staff->name,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'hours_worked' => round($hoursWorked, 2),
            'hourly_rate' => $hourlyRate,
            'gross_earnings' => round($gross, 2),
            'net_payout' => round(max(0, $gross), 2),
        ];
    }

    /**
     * Generate an approved payout for a staff member covering only their unpaid timecards
     * within the given date range. Returns null when there are no payer timecards.
     */
    public function generatePayoutForRange(Staff $staff, $startDate, $endDate, ?int $approverId = null): ?StaffPayout
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $attendances = StaffAttendance::where('staff_id', $staff->id)
            ->whereBetween('clock_in', [$start, $end])
            ->whereNotNull('clock_out')
            ->whereNotNull('total_hours')
            ->whereNull('payout_id')
            ->get();

        if ($attendances->isEmpty()) {
            return null;
        }

        $hoursWorked = (float) $attendances->sum('total_hours');
        $rate = (float) ($staff->salary ?? 0.00);

        $gross = (float) $attendances->sum(function (StaffAttendance $attendance) use ($rate) {
            if ($attendance->shift_earnings !== null) {
                return (float) $attendance->shift_earnings;
            }

            return (float) $attendance->total_hours * $rate;
        });

        if ($hoursWorked <= 0 || $gross <= 0) {
            return null;
        }

        $endDateCarbon = Carbon::parse($endDate);

        $payout = StaffPayout::create([
            'staff_id' => $staff->id,
            'branch_id' => $staff->branch_id,
            'year' => $endDateCarbon->isoWeekYear,
            'week_number' => $endDateCarbon->isoWeek(),
            'start_date' => Carbon::parse($startDate)->toDateString(),
            'end_date' => $endDateCarbon->toDateString(),
            'hours_worked' => round($hoursWorked, 2),
            'hourly_rate' => $rate,
            'gross_earnings' => round($gross, 2),
            'net_payout' => round(max(0, $gross), 2),
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $approverId,
        ]);

        $payout->attendance()->saveMany($attendances);

        return $payout;
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

            $periodLabel = ($payout->start_date && $payout->end_date)
                ? $payout->start_date . ' to ' . $payout->end_date
                : "Week {$payout->week_number}, {$payout->year}";

            $transfer = $stripe->transfers->create([
                'amount' => $amountInCents,
                'currency' => 'gbp',
                'destination' => $staff->stripe_account_id,
                'description' => 'Staff Salary Payout - ' . $periodLabel,
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
<?php

namespace App\Services;

use App\Models\StaffAttendance;
use Carbon\Carbon;

class StaffAttendanceService
{
    /**
     * Calculate total hours between clock in and clock out timestamps.
     */
    public function calculateTotalHours($clockIn, $clockOut): float
    {
        $start = Carbon::parse($clockIn);
        $end = Carbon::parse($clockOut);

        $minutes = $start->diffInMinutes($end);

        return round($minutes / 60, 2);
    }

    /**
     * Snapshot the hourly rate and accrue the shift earnings for a timecard.
     * Shift Gross Wage = Net Hours * Hourly Rate (staff.salary unless overridden).
     */
    public function applyShiftEarnings(StaffAttendance $attendance, ?float $rateOverride = null): void
    {
        $hours = (float) ($attendance->total_hours ?? 0.00);

        if ($attendance->payout_id || $hours <= 0) {
            return;
        }

        $rate = $rateOverride ?? (float) ($attendance->staff?->salary ?? 0.00);
        $earnings = round($hours * $rate, 2);

        $attendance->update([
            'hourly_rate' => $rate,
            'shift_earnings' => $earnings,
        ]);
    }
}

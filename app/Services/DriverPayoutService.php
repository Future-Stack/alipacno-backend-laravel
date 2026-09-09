<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryFeeTier;
use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\DriverShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class DriverPayoutService
{
    /**
     * Calculate and return calculated payout breakdown for a driver for a specific week without saving.
     */
    public function calculateWeeklyEarnings(Driver $driver, Carbon $startDate, Carbon $endDate): array
    {
        // 1. Shift hours worked during the week
        $shifts = DriverShift::where('driver_id', $driver->id)
            ->whereBetween('clock_in_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->get();

        $hoursWorked = (float) $shifts->sum('total_hours');
        $hourlyRate = (float) ($driver->hourly_rate ?? 0.00);
        $hourlyEarnings = round($hoursWorked * $hourlyRate, 2);

        // 2. Deliveries completed during the week
        $deliveries = Delivery::where('driver_id', $driver->id)
            ->where('delivery_status', 'delivered')
            ->whereBetween('delivered_time', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->with('order')
            ->get();

        $deliveryFees = 0.00;
        $tips = 0.00;
        $cashCollected = 0.00;

        foreach ($deliveries as $delivery) {
            // Drop fee calculation based on distance tier
            $distMiles = (float) ($delivery->distance_miles ?? 0.00);
            if ($distMiles <= 0 && $delivery->order) {
                $distKm = $delivery->order->calculateDistanceKm();
                $distMiles = round($distKm * 0.621371, 2);
            }

            $fee = (float) ($delivery->driver_fee > 0
                ? $delivery->driver_fee
                : DeliveryFeeTier::calculateFeeForDistance($distMiles));

            $deliveryFees += $fee;

            // Tips from order
            if ($delivery->order) {
                $tips += (float) ($delivery->order->rider_tip ?? $delivery->order->tip ?? 0.00);

                // COD Cash collection
                $isCod = $delivery->is_cod || strtolower($delivery->order->payment_method ?? '') === 'cod' || strtolower($delivery->order->payment_method ?? '') === 'cash';
                if ($isCod) {
                    $cash = (float) ($delivery->cash_collected > 0 ? $delivery->cash_collected : $delivery->order->total ?? 0.00);
                    $cashCollected += $cash;
                }
            }
        }

        $grossEarnings = round($hourlyEarnings + $deliveryFees + $tips, 2);
        $netPayout = round(max(0, $grossEarnings - $cashCollected), 2);

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'hours_worked' => round($hoursWorked, 2),
            'hourly_rate' => $hourlyRate,
            'hourly_earnings' => $hourlyEarnings,
            'completed_drops' => $deliveries->count(),
            'delivery_fees' => round($deliveryFees, 2),
            'tips' => round($tips, 2),
            'gross_earnings' => $grossEarnings,
            'cash_collected' => round($cashCollected, 2),
            'net_payout' => $netPayout,
        ];
    }

    /**
     * Generate or update DriverPayout record for a specific driver and week.
     */
    public function generateWeeklyPayout(Driver $driver, int $year, int $weekNumber): DriverPayout
    {
        $date = Carbon::now()->setISODate($year, $weekNumber);
        $startDate = $date->copy()->startOfWeek();
        $endDate = $date->copy()->endOfWeek();

        $calc = $this->calculateWeeklyEarnings($driver, $startDate, $endDate);

        return DriverPayout::updateOrCreate(
            [
                'driver_id' => $driver->id,
                'year' => $year,
                'week_number' => $weekNumber,
            ],
            [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'hours_worked' => $calc['hours_worked'],
                'hourly_rate' => $calc['hourly_rate'],
                'hourly_earnings' => $calc['hourly_earnings'],
                'delivery_fees' => $calc['delivery_fees'],
                'tips' => $calc['tips'],
                'gross_earnings' => $calc['gross_earnings'],
                'cash_collected' => $calc['cash_collected'],
                'net_payout' => $calc['net_payout'],
                'status' => 'pending',
            ]
        );
    }

    /**
     * Process weekly payouts for ALL drivers for the previous week (Week N - 1 week lag).
     */
    public function processLaggedWeeklyPayouts(): array
    {
        // 1-Week Payment Lag: Previous week relative to current date
        $previousWeek = Carbon::now()->subWeek();
        $year = $previousWeek->year;
        $weekNumber = $previousWeek->weekOfYear;

        $drivers = Driver::where('status', '!=', 'offline')->orWhereHas('deliveries')->get();
        $processed = [];

        foreach ($drivers as $driver) {
            $payout = $this->generateWeeklyPayout($driver, $year, $weekNumber);
            if ($payout->net_payout > 0 && $payout->status === 'pending') {
                $this->executeStripeTransfer($payout);
            }
            $processed[] = $payout;
        }

        return $processed;
    }

    /**
     * Execute Stripe Connect Transfer to driver if Stripe is configured.
     */
    public function executeStripeTransfer(DriverPayout $payout): bool
    {
        $driver = $payout->driver;
        if (!$driver || !$driver->stripe_account_id || !$driver->stripe_onboarding_completed) {
            Log::info("Driver payout {$payout->id} pending manual payment or Stripe setup for driver {$driver->name}.");
            return false;
        }

        if ($payout->stripe_transfer_id || $payout->status === 'paid') {
            Log::info("Driver payout {$payout->id} already paid; skipping Stripe transfer to avoid duplicate payment.");
            return true;
        }

        $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');
        if (!$stripeSecret) {
            Log::warning("Stripe secret key not configured for payout {$payout->id}.");
            return false;
        }

        try {
            $stripe = new StripeClient($stripeSecret);

            // Transfer net payout amount in cents/pence (e.g. £10.00 -> 1000)
            $amountInCents = (int) round($payout->net_payout * 100);

            $transfer = $stripe->transfers->create([
                'amount' => $amountInCents,
                'currency' => 'gbp',
                'destination' => $driver->stripe_account_id,
                'description' => "Weekly Driver Payout - Week {$payout->week_number}, {$payout->year}",
            ]);

            $payout->update([
                'status' => 'paid',
                'stripe_transfer_id' => $transfer->id,
                'paid_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to execute Stripe Connect transfer for payout {$payout->id}: " . $e->getMessage());
            $payout->update(['status' => 'failed', 'notes' => $e->getMessage()]);
            return false;
        }
    }
}

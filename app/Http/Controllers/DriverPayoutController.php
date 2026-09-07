<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverPayout;
use App\Services\DriverPayoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Stripe\StripeClient;

class DriverPayoutController extends Controller
{
    protected DriverPayoutService $payoutService;

    public function __construct(DriverPayoutService $payoutService)
    {
        $this->payoutService = $payoutService;
    }

    /**
     * Helper to verify and sync Stripe Connect Express onboarding status directly from Stripe API.
     */
    protected function syncStripeStatus(Driver $driver): bool
    {
        if (!$driver->stripe_account_id) {
            return false;
        }

        if ($driver->stripe_onboarding_completed) {
            return true;
        }

        $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');
        if (!$stripeSecret) {
            return false;
        }

        try {
            $stripe = new StripeClient($stripeSecret);
            $account = $stripe->accounts->retrieve($driver->stripe_account_id);

            if (!empty($account->details_submitted) || !empty($account->payouts_enabled)) {
                $driver->update([
                    'stripe_onboarding_completed' => true,
                ]);
                return true;
            }
        } catch (\Exception $e) {
            // Ignore API exceptions if account lookup fails
        }

        return (bool) $driver->stripe_onboarding_completed;
    }

    /**
     * Admin Dashboard: List weekly payouts across drivers.
     */
    public function index(Request $request)
    {
        $query = DriverPayout::with('driver.branch');

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }

        if ($request->filled('year')) {
            $query->where('year', $request->input('year'));
        }

        if ($request->filled('week_number')) {
            $query->where('week_number', $request->input('week_number'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $payouts = $query->orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'status' => 200,
            'data' => $payouts,
        ]);
    }

    /**
     * Admin Dashboard: Calculate / recalculate payouts for a specific week.
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'driver_id' => 'nullable|exists:drivers,id',
            'year' => 'nullable|integer',
            'week_number' => 'nullable|integer',
        ]);

        $year = (int) ($request->input('year') ?? now()->year);
        $weekNumber = (int) ($request->input('week_number') ?? now()->subWeek()->weekOfYear);

        if ($request->filled('driver_id')) {
            $driver = Driver::findOrFail($request->input('driver_id'));
            $payout = $this->payoutService->generateWeeklyPayout($driver, $year, $weekNumber);
            return response()->json([
                'status' => 200,
                'data' => $payout,
                'message' => 'Weekly payout calculated successfully.',
            ]);
        }

        $drivers = Driver::all();
        $payouts = [];
        foreach ($drivers as $driver) {
            $payouts[] = $this->payoutService->generateWeeklyPayout($driver, $year, $weekNumber);
        }

        return response()->json([
            'status' => 200,
            'data' => $payouts,
            'message' => 'Weekly payouts calculated for all drivers.',
        ]);
    }

    /**
     * Admin Dashboard: Process payout via Stripe Connect Express or mark manual payment.
     */
    public function process(Request $request, DriverPayout $driverPayout)
    {
        if ($driverPayout->status === 'paid') {
            return response()->json([
                'status' => 400,
                'message' => 'Payout has already been paid.',
            ], 400);
        }

        $success = $this->payoutService->executeStripeTransfer($driverPayout);

        if ($success) {
            return response()->json([
                'status' => 200,
                'data' => $driverPayout->fresh(),
                'message' => 'Stripe Connect payout transfer executed successfully.',
            ]);
        }

        // If Stripe transfer is not configured or failed, allow marking manual cash/bank payment
        if ($request->input('manual_payment', false)) {
            $driverPayout->update([
                'status' => 'paid',
                'paid_at' => now(),
                'notes' => 'Marked as paid manually by admin.',
            ]);

            return response()->json([
                'status' => 200,
                'data' => $driverPayout->fresh(),
                'message' => 'Payout marked as paid manually.',
            ]);
        }

        return response()->json([
            'status' => 422,
            'message' => 'Unable to execute Stripe payout. Driver might not have set up Stripe account or key is missing.',
        ], 422);
    }

    /**
     * Driver App: View breakdown of driver earnings & shift stats.
     */
    public function driverEarnings(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user?->id)->first();

        if (!$driver) {
            $driver = Driver::where('id', $request->input('driver_id'))->first();
        }

        if (!$driver) {
            return response()->json(['status' => 404, 'message' => 'Driver profile not found.'], 404);
        }

        // Auto-sync Stripe onboarding status
        $this->syncStripeStatus($driver);

        // Current week calculation
        $currentWeekStart = Carbon::now()->startOfWeek();
        $currentWeekEnd = Carbon::now()->endOfWeek();
        $currentEarnings = $this->payoutService->calculateWeeklyEarnings($driver, $currentWeekStart, $currentWeekEnd);

        // Previous week calculation (Week N for 1-week payment lag)
        $previousWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $previousWeekEnd = Carbon::now()->subWeek()->endOfWeek();
        $previousEarnings = $this->payoutService->calculateWeeklyEarnings($driver, $previousWeekStart, $previousWeekEnd);

        // Payout history
        $payoutHistory = DriverPayout::where('driver_id', $driver->id)
            ->orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 200,
            'data' => [
                'stripe_onboarding_completed' => (bool) $driver->fresh()->stripe_onboarding_completed,
                'current_week' => $currentEarnings,
                'previous_week_lagged' => $previousEarnings,
                'payout_history' => $payoutHistory,
            ],
        ]);
    }

    /**
     * Check & sync Stripe Connect onboarding completion status for driver.
     */
    public function stripeStatus(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user?->id)->first();

        if (!$driver) {
            $driver = Driver::where('id', $request->input('driver_id'))->first();
        }

        if (!$driver) {
            return response()->json(['status' => 404, 'message' => 'Driver profile not found.'], 404);
        }

        $isCompleted = $this->syncStripeStatus($driver);
        $freshDriver = $driver->fresh();

        return response()->json([
            'status' => 200,
            'data' => [
                'driver_id' => $freshDriver->id,
                'stripe_account_id' => $freshDriver->stripe_account_id,
                'stripe_onboarding_completed' => (bool) $freshDriver->stripe_onboarding_completed,
            ],
            'message' => $isCompleted
                ? 'Stripe onboarding completed successfully.'
                : 'Stripe onboarding is pending or incomplete.',
        ]);
    }

    /**
     * Generate Stripe Connect Express onboarding link for driver.
     */
    public function stripeOnboard(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user?->id)->first();

        if (!$driver) {
            $driver = Driver::where('id', $request->input('driver_id'))->first();
        }

        if (!$driver) {
            return response()->json(['status' => 404, 'message' => 'Driver profile not found.'], 404);
        }

        // Auto sync first if driver completed it previously
        if ($driver->stripe_account_id && !$driver->stripe_onboarding_completed) {
            $this->syncStripeStatus($driver);
            $driver->refresh();
        }

        $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');
        if (!$stripeSecret) {
            return response()->json(['status' => 500, 'message' => 'Stripe secret key not configured.'], 500);
        }

        try {
            $stripe = new StripeClient($stripeSecret);
            $refreshUrl = $request->input('refresh_url', url('/api/v1/drivers/stripe-onboard'));
            $returnUrl = $request->input('return_url', url('/api/v1/drivers/earnings'));

            // 1. If driver already has a Stripe account ID, try generating onboarding link
            if ($driver->stripe_account_id) {
                try {
                    $accountLink = $stripe->accountLinks->create([
                        'account' => $driver->stripe_account_id,
                        'refresh_url' => $refreshUrl,
                        'return_url' => $returnUrl,
                        'type' => 'account_onboarding',
                    ]);

                    return response()->json([
                        'status' => 200,
                        'data' => [
                            'onboarding_url' => $accountLink->url,
                            'stripe_account_id' => $driver->stripe_account_id,
                            'stripe_onboarding_completed' => (bool) $driver->stripe_onboarding_completed,
                        ],
                    ]);
                } catch (\Exception $e) {
                    // Reset invalid account ID to allow fresh creation below
                    $driver->update(['stripe_account_id' => null, 'stripe_onboarding_completed' => false]);
                }
            }

            // 2. Try Stripe Accounts v2 API creation
            $accountId = null;
            try {
                $v2Response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                    'Content-Type' => 'application/json',
                ])->post('https://api.stripe.com/v2/core/accounts', [
                    'contact_email' => $driver->user?->email ?? "driver{$driver->id}@alipacino.com",
                    'identity' => [
                        'country' => 'gb',
                    ],
                    'dashboard' => [
                        'type' => 'express',
                    ],
                ]);

                if ($v2Response->successful() && $v2Response->json('id')) {
                    $accountId = $v2Response->json('id');
                }
            } catch (\Exception $e) {
                // Ignore v2 failure and use v1 fallback
            }

            // 3. Fallback: Stripe Accounts v1 creation with controller / express type
            if (!$accountId) {
                try {
                    $account = $stripe->accounts->create([
                        'controller' => [
                            'stripe_dashboard' => ['type' => 'express'],
                            'fees' => ['payer' => 'application'],
                            'losses' => ['payments' => 'application'],
                        ],
                        'country' => 'GB',
                        'email' => $driver->user?->email ?? "driver{$driver->id}@alipacino.com",
                        'capabilities' => [
                            'transfers' => ['requested' => true],
                        ],
                    ]);
                    $accountId = $account->id;
                } catch (\Exception $e) {
                    $account = $stripe->accounts->create([
                        'type' => 'express',
                        'country' => 'GB',
                        'email' => $driver->user?->email ?? "driver{$driver->id}@alipacino.com",
                        'capabilities' => [
                            'transfers' => ['requested' => true],
                        ],
                    ]);
                    $accountId = $account->id;
                }
            }

            $driver->update(['stripe_account_id' => $accountId, 'stripe_onboarding_completed' => false]);

            $accountLink = $stripe->accountLinks->create([
                'account' => $accountId,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);

            return response()->json([
                'status' => 200,
                'data' => [
                    'onboarding_url' => $accountLink->url,
                    'stripe_account_id' => $accountId,
                    'stripe_onboarding_completed' => false,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Stripe onboarding creation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    //Onboarding Webhooks
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.onboarding_webhook_secret') ?? env('ONBOARDING_WEBHOOK_SECRET');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Listen for account updates
        if ($event->type === 'account.updated') {
            $account = $event->data->object; // Contains the Stripe Account object

            $driver = Driver::where('stripe_account_id', $account->id)->first();

            if ($driver) {
                // Check if onboarding/payout requirements are fulfilled
                if (!empty($account->details_submitted) || !empty($account->payouts_enabled)) {
                    $driver->update([
                        'stripe_onboarding_completed' => true,
                    ]);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}

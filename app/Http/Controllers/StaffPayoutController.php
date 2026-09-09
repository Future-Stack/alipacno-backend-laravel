<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffPayout;
use App\Services\StaffPayoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Stripe\StripeClient;

class StaffPayoutController extends Controller
{
    protected StaffPayoutService $payoutService;

    public function __construct(StaffPayoutService $payoutService)
    {
        $this->payoutService = $payoutService;
    }

    /**
     * Helper to verify and sync Stripe Connect Express onboarding status directly from Stripe API.
     */
    protected function syncStripeStatus(Staff $staff): bool
    {
        if (!$staff->stripe_account_id) {
            return false;
        }

        if ($staff->stripe_onboarding_completed) {
            return true;
        }

        $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');
        if (!$stripeSecret) {
            return false;
        }

        try {
            $stripe = new StripeClient($stripeSecret);
            $account = $stripe->accounts->retrieve($staff->stripe_account_id);

            if (!empty($account->details_submitted) || !empty($account->payouts_enabled)) {
                $staff->update(['stripe_onboarding_completed' => true]);
                return true;
            }
        } catch (\Exception $e) {
            // Ignore API exceptions if account lookup fails
        }

        return (bool) $staff->stripe_onboarding_completed;
    }

    /**
     * Admin / Branch Admin: List staff payouts, optionally filtered by branch or staff.
     */
    public function index(Request $request)
    {
        $query = StaffPayout::with('staff.branch', 'staff.role');

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->input('staff_id'));
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('staff', function ($q) use ($request) {
                $q->where('branch_id', $request->input('branch_id'));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereDate('start_date', '>=', $request->input('start_date'))
                ->whereDate('end_date', '<=', $request->input('end_date'));
        }

        $payouts = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'status' => 200,
            'data' => $payouts,
        ]);
    }

    /**
     * Branch Admin: Calculate / preview a staff member's payout for a specific ISO week (does not save).
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'year' => 'nullable|integer',
            'week_number' => 'nullable|integer',
        ]);

        $staff = Staff::findOrFail($validated['staff_id']);

        $year = (int) ($validated['year'] ?? now()->year);
        $weekNumber = (int) ($validated['week_number'] ?? now()->subWeek()->weekOfYear);

        $date = Carbon::now()->setISODate($year, $weekNumber);
        $start = $date->copy()->startOfWeek();
        $end = $date->copy()->endOfWeek();

        $calc = $this->payoutService->calculateEarnings($staff, $start, $end);

        // Include any previously saved payout for this week for reference
        $existing = StaffPayout::where('staff_id', $staff->id)
            ->where('year', $year)
            ->where('week_number', $weekNumber)
            ->first();

        return response()->json([
            'status' => 200,
            'data' => $calc,
            'existing_payout' => $existing,
            'message' => 'Staff payout calculated successfully.',
        ]);
    }

    /**
     * Branch Admin: Process a payout via Stripe Connect, or mark as manual payment.
     */
    public function process(Request $request, StaffPayout $staffPayout)
    {
        if ($staffPayout->status === 'paid') {
            return response()->json([
                'status' => 400,
                'message' => 'Payout has already been paid.',
            ], 400);
        }

        $success = $this->payoutService->executeStripeTransfer($staffPayout);

        if ($success) {
            return response()->json([
                'status' => 200,
                'data' => $staffPayout->fresh()->load('staff'),
                'message' => 'Stripe Connect payout transfer executed successfully.',
            ]);
        }

        if ($request->input('manual_payment', false)) {
            $staffPayout->update([
                'status' => 'paid',
                'paid_at' => now(),
                'notes' => 'Marked as paid manually by admin.',
            ]);

            return response()->json([
                'status' => 200,
                'data' => $staffPayout->fresh()->load('staff'),
                'message' => 'Payout marked as paid manually.',
            ]);
        }

        return response()->json([
            'status' => 422,
            'message' => 'Unable to execute Stripe payout. Staff member might not have set up Stripe account or key is missing.',
        ], 422);
    }

    /**
     * Branch Admin: Process weekly staff payouts for all staff (previous week lag by default,
     * or specify year/week_number). Mirrors the scheduled `staff:process-weekly-payouts` command.
     */
    public function processWeekly(Request $request)
    {
        $validated = $request->validate([
            'year' => 'nullable|integer',
            'week_number' => 'nullable|integer',
        ]);

        if (!empty($validated['year']) && !empty($validated['week_number'])) {
            $year = (int) $validated['year'];
            $weekNumber = (int) $validated['week_number'];

            $staffMembers = Staff::where('status', '!=', 'off_duty')->orWhereHas('attendances')->get();
            $processed = [];

            foreach ($staffMembers as $staff) {
                $payout = $this->payoutService->generateWeeklyPayout($staff, $year, $weekNumber);
                if ($payout->net_payout > 0 && $payout->status === 'pending') {
                    $this->payoutService->executeStripeTransfer($payout);
                }
                $processed[] = $payout;
            }

            return response()->json([
                'status' => 200,
                'data' => $processed,
                'message' => "Staff weekly payouts processed for week {$weekNumber}, {$year}.",
            ]);
        }

        $processed = $this->payoutService->processLaggedWeeklyPayouts();

        return response()->json([
            'status' => 200,
            'data' => $processed,
            'message' => 'Staff weekly payouts processed for the previous week.',
        ]);
    }

    /**
     * Staff Stripe onboarding status check & sync.
     */
    public function stripeStatus(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
        ]);

        $staff = Staff::findOrFail($validated['staff_id']);
        $isCompleted = $this->syncStripeStatus($staff);
        $freshStaff = $staff->fresh();

        return response()->json([
            'status' => 200,
            'data' => [
                'staff_id' => $freshStaff->id,
                'stripe_account_id' => $freshStaff->stripe_account_id,
                'stripe_onboarding_completed' => (bool) $freshStaff->stripe_onboarding_completed,
            ],
            'message' => $isCompleted
                ? 'Stripe onboarding completed successfully.'
                : 'Stripe onboarding is pending or incomplete.',
        ]);
    }

    /**
     * Generate a Stripe Connect Express onboarding link for a staff member / list existing status.
     */
    public function stripeOnboard(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
        ]);

        $staff = Staff::findOrFail($validated['staff_id']);

        // Auto sync first if staff completed it previously
        if ($staff->stripe_account_id && !$staff->stripe_onboarding_completed) {
            $this->syncStripeStatus($staff);
            $staff->refresh();
        }

        $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');
        if (!$stripeSecret) {
            return response()->json(['status' => 500, 'message' => 'Stripe secret key not configured.'], 500);
        }

        try {
            $stripe = new StripeClient($stripeSecret);
            $refreshUrl = $request->input('refresh_url', url('/api/v1/staff-payouts/stripe-onboard'));
            $returnUrl = $request->input('return_url', url('/api/v1/staff-payouts'));

            // 1. If staff already has an account, generate onboarding link
            if ($staff->stripe_account_id) {
                try {
                    $accountLink = $stripe->accountLinks->create([
                        'account' => $staff->stripe_account_id,
                        'refresh_url' => $refreshUrl,
                        'return_url' => $returnUrl,
                        'type' => 'account_onboarding',
                    ]);

                    return response()->json([
                        'status' => 200,
                        'data' => [
                            'onboarding_url' => $accountLink->url,
                            'stripe_account_id' => $staff->stripe_account_id,
                            'stripe_onboarding_completed' => (bool) $staff->stripe_onboarding_completed,
                        ],
                    ]);
                } catch (\Exception $e) {
                    // Reset invalid account ID to allow fresh creation below
                    $staff->update(['stripe_account_id' => null, 'stripe_onboarding_completed' => false]);
                }
            }

            // 2. Try Stripe Accounts v2 API creation
            $accountId = null;
            try {
                $v2Response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $stripeSecret,
                    'Content-Type' => 'application/json',
                ])->post('https://api.stripe.com/v2/core/accounts', [
                    'contact_email' => $staff->email ?? "staff{$staff->id}@alipacino.com",
                    'identity' => ['country' => 'gb'],
                    'dashboard' => ['type' => 'express'],
                ]);

                if ($v2Response->successful() && $v2Response->json('id')) {
                    $accountId = $v2Response->json('id');
                }
            } catch (\Exception $e) {
                // Ignore v2 failure and use v1 fallback
            }

            // 3. Fallback: Stripe Accounts v1 creation
            if (!$accountId) {
                try {
                    $account = $stripe->accounts->create([
                        'controller' => [
                            'stripe_dashboard' => ['type' => 'express'],
                            'fees' => ['payer' => 'application'],
                            'losses' => ['payments' => 'application'],
                        ],
                        'country' => 'GB',
                        'email' => $staff->email ?? "staff{$staff->id}@alipacino.com",
                        'capabilities' => ['transfers' => ['requested' => true]],
                    ]);
                    $accountId = $account->id;
                } catch (\Exception $e) {
                    $account = $stripe->accounts->create([
                        'type' => 'express',
                        'country' => 'GB',
                        'email' => $staff->email ?? "staff{$staff->id}@alipacino.com",
                        'capabilities' => ['transfers' => ['requested' => true]],
                    ]);
                    $accountId = $account->id;
                }
            }

            $staff->update(['stripe_account_id' => $accountId, 'stripe_onboarding_completed' => false]);

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

    /**
     * Stripe onboarding webhook.
     */
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

        if ($event->type === 'account.updated') {
            $account = $event->data->object;

            $staff = Staff::where('stripe_account_id', $account->id)->first();

            if ($staff) {
                if (!empty($account->details_submitted) || !empty($account->payouts_enabled)) {
                    $staff->update(['stripe_onboarding_completed' => true]);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}

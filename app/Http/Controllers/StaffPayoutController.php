<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffPayout;
use App\Services\StaffPayoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * Branch Admin: Calculate / preview a staff member's earnings for a date range or ISO week (does not save).
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'year' => 'nullable|integer',
            'week_number' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $staff = Staff::findOrFail($validated['staff_id']);

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $start = Carbon::parse($validated['start_date'] ?? $validated['end_date'])->startOfDay();
            $end = Carbon::parse($validated['end_date'] ?? $validated['start_date'])->endOfDay();
            $year = (int) $end->isoWeekYear;
            $weekNumber = (int) $end->isoWeek();
        } else {
            $year = (int) ($validated['year'] ?? now()->year);
            $weekNumber = (int) ($validated['week_number'] ?? now()->subWeek()->isoWeek());

            $date = Carbon::now()->setISODate($year, $weekNumber);
            $start = $date->copy()->startOfWeek();
            $end = $date->copy()->endOfWeek();
        }

        $calc = $this->payoutService->calculateEarnings($staff, $start, $end);

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
     * Branch Admin / Staff Management: Approve AND pay a single staff member's
     * accrued timecards for any date range (1 day or multiple days) in one action.
     */
    public function approve(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
            'manual_payment' => 'nullable|boolean',
        ]);

        $staff = Staff::with('driver')->findOrFail($validated['staff_id']);

        if ($staff->driver) {
            return response()->json([
                'status' => 422,
                'message' => 'Driver-linked staff cannot be paid through the staff payout flow.',
            ], 422);
        }

        $payout = $this->payoutService->generatePayoutForRange(
            $staff,
            $validated['start_date'],
            $validated['end_date'],
            $request->user()?->id
        );

        if (!$payout) {
            return response()->json([
                'status' => 422,
                'message' => 'No payable (unpaid) timecards found for this staff member in the given range.',
            ], 422);
        }

        $payout->update(['notes' => $validated['notes'] ?? null]);

        if (!empty($validated['manual_payment'])) {
            $payout->update([
                'status' => 'paid',
                'paid_at' => now(),
                'notes' => trim((string) ($validated['notes'] ?? '') . ' Marked as paid manually by admin.'),
            ]);

            return response()->json([
                'status' => 200,
                'data' => $payout->fresh()->load('staff', 'attendance'),
                'message' => 'Payout approved and marked as paid manually.',
            ]);
        }

        if (!$staff->stripe_account_id || !$staff->stripe_onboarding_completed) {
            return response()->json([
                'status' => 422,
                'data' => $payout->fresh()->load('staff', 'attendance'),
                'message' => 'Payout approved but Stripe transfer skipped. Staff member must complete Stripe onboarding first.',
            ], 422);
        }

        $success = $this->payoutService->executeStripeTransfer($payout);

        if ($success) {
            return response()->json([
                'status' => 200,
                'data' => $payout->fresh()->load('staff', 'attendance'),
                'message' => 'Payout approved and paid successfully.',
            ]);
        }

        return response()->json([
            'status' => 422,
            'data' => $payout->fresh()->load('staff', 'attendance'),
            'message' => 'Payout approved but the Stripe transfer could not be completed. Review the payout record or process manually.',
        ], 422);
    }

    /**
     * Branch Admin / Staff Management: Approve AND pay accrued timecards for all
     * eligible staff in a branch for any date range, in one batch action.
     */
    public function approveBatch(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'integer|exists:staff,id',
            'manual_payment' => 'nullable|boolean',
        ]);

        $staffQuery = Staff::where('branch_id', $validated['branch_id'])
            ->where('status', '!=', 'off_duty')
            ->whereDoesntHave('driver');

        if (!empty($validated['staff_ids'])) {
            $staffQuery->whereIn('id', $validated['staff_ids']);
        }

        $staffMembers = $staffQuery->get();

        $paid = [];
        $skipped = [];
        $errors = [];

        foreach ($staffMembers as $staff) {
            $payout = $this->payoutService->generatePayoutForRange(
                $staff,
                $validated['start_date'],
                $validated['end_date'],
                $request->user()?->id
            );

            if (!$payout) {
                continue;
            }

            if (!empty($validated['manual_payment'])) {
                $payout->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'notes' => 'Marked as paid manually by admin.',
                ]);
                $paid[] = $payout->load('staff', 'attendance');
                continue;
            }

            if (!$staff->stripe_account_id || !$staff->stripe_onboarding_completed) {
                $skipped[] = [
                    'staff_id' => $staff->id,
                    'staff_name' => $staff->name,
                    'payout_id' => $payout->id,
                    'reason' => 'Stripe onboarding incomplete.',
                ];
                continue;
            }

            if ($this->payoutService->executeStripeTransfer($payout)) {
                $paid[] = $payout->fresh()->load('staff', 'attendance');
            } else {
                $errors[] = [
                    'staff_id' => $staff->id,
                    'staff_name' => $staff->name,
                    'payout_id' => $payout->id,
                    'reason' => 'Stripe transfer failed. Review the payout record or process manually.',
                ];
            }
        }

        return response()->json([
            'status' => 200,
            'data' => [
                'branch_id' => $validated['branch_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'paid' => $paid,
                'skipped' => $skipped,
                'errors' => $errors,
                'summary' => [
                    'paid_count' => count($paid),
                    'skipped_count' => count($skipped),
                    'error_count' => count($errors),
                    'total_paid' => round(array_sum(array_map(fn ($payout) => (float) $payout->net_payout, $paid)), 2),
                ],
            ],
            'message' => count($paid)
                ? 'Batch payout approved and processed.'
                : 'No payouts were processed. Review skipped and error lists.',
        ]);
    }

    /**
     * Branch Admin / Staff Management: Payroll review workbench for a branch.
     * Lists unpaid accrued timecards per staff, missing clock-outs, and paid history.
     */
    public function payrollReview(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($validated['start_date'] ?? now()->startOfWeek()->toDateString())->startOfDay();
        $end = Carbon::parse($validated['end_date'] ?? now()->endOfWeek()->toDateString())->endOfDay();

        $attendances = StaffAttendance::with('staff:id,name,employee_id,branch_id,status,salary')
            ->whereBetween('clock_in', [$start, $end])
            ->whereHas('staff', function ($q) use ($validated) {
                $q->where('branch_id', $validated['branch_id'])
                    ->where('status', '!=', 'off_duty')
                    ->whereDoesntHave('driver');
            })
            ->get();

        $missingClockOuts = StaffAttendance::whereBetween('clock_in', [$start, $end])
            ->whereNull('clock_out')
            ->whereHas('staff', function ($q) use ($validated) {
                $q->where('branch_id', $validated['branch_id'])
                    ->where('status', '!=', 'off_duty')
                    ->whereDoesntHave('driver');
            })
            ->get()
            ->groupBy('staff_id')
            ->map->count();

        $staffPayable = $attendances
            ->whereNull('payout_id')
            ->whereNotNull('clock_out')
            ->whereNotNull('total_hours')
            ->groupBy('staff_id')
            ->map(function ($rows) use ($missingClockOuts) {
                $staff = $rows->first()->staff;
                $hours = (float) $rows->sum('total_hours');
                $gross = (float) $rows->sum(function ($row) {
                    if ($row->shift_earnings !== null) {
                        return (float) $row->shift_earnings;
                    }

                    return (float) $row->total_hours * (float) ($row->staff?->salary ?? 0.00);
                });

                return [
                    'staff_id' => $staff->id,
                    'staff_name' => $staff->name,
                    'employee_id' => $staff->employee_id,
                    'shift_count' => $rows->count(),
                    'hours_worked' => round($hours, 2),
                    'gross_earnings' => round($gross, 2),
                    'missing_clock_outs' => (int) $missingClockOuts->get($staff->id, 0),
                ];
            })
            ->keyBy('staff_id');

        foreach ($missingClockOuts as $staffId => $count) {
            if ($staffPayable->has($staffId)) {
                continue;
            }

            $staff = Staff::find($staffId);
            if (!$staff) {
                continue;
            }

            $staffPayable->put($staffId, [
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'employee_id' => $staff->employee_id,
                'shift_count' => 0,
                'hours_worked' => 0.0,
                'gross_earnings' => 0.0,
                'missing_clock_outs' => (int) $count,
            ]);
        }

        $staffPayable = $staffPayable->values();

        $paidHistory = StaffPayout::with('staff:id,name')
            ->where('branch_id', $validated['branch_id'])
            ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('id')
            ->get()
            ->map(fn (StaffPayout $payout) => [
                'payout_id' => $payout->id,
                'staff_id' => $payout->staff_id,
                'staff_name' => $payout->staff?->name,
                'start_date' => $payout->start_date?->toDateString(),
                'end_date' => $payout->end_date?->toDateString(),
                'hours_worked' => $payout->hours_worked,
                'gross_earnings' => $payout->gross_earnings,
                'net_payout' => $payout->net_payout,
                'status' => $payout->status,
                'paid_at' => $payout->paid_at?->toDateTimeString(),
            ]);

        return response()->json([
            'status' => 200,
            'data' => [
                'branch_id' => $validated['branch_id'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'staff_payable' => $staffPayable,
                'total_unpaid_gross' => round((float) $staffPayable->sum('gross_earnings'), 2),
                'paid_history' => $paidHistory,
            ],
        ]);
    }

    /**
     * Staff App: Individual salary ledger / payout history for the authenticated staff member.
     */
    public function salaryHistory(Request $request)
    {
        $user = Auth::user();
        $staff = Staff::where('user_id', $user?->id)->first();

        if (!$staff) {
            return response()->json(['status' => 404, 'message' => 'Staff profile not found.'], 404);
        }

        $history = StaffPayout::withCount('attendance')
            ->where('staff_id', $staff->id)
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 15));

        $totals = StaffPayout::where('staff_id', $staff->id)
            ->where('status', 'paid')
            ->selectRaw('COALESCE(SUM(hours_worked), 0) as total_hours, COALESCE(SUM(net_payout), 0) as total_net')
            ->first();

        return response()->json([
            'status' => 200,
            'data' => [
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'totals' => [
                    'paid_hours' => (float) ($totals->total_hours ?? 0),
                    'net_paid' => (float) ($totals->total_net ?? 0),
                ],
                'history' => $history,
            ],
        ]);
    }

    /**
     * Staff App: View breakdown of staff earnings & attendance stats.
     */
    public function staffEarnings(Request $request)
    {
        $user = Auth::user();
        $staff = Staff::where('user_id', $user?->id)->first();

        if (!$staff) {
            $staff = Staff::where('id', $request->input('staff_id'))->first();
        }

        if (!$staff) {
            return response()->json(['status' => 404, 'message' => 'Staff profile not found.'], 404);
        }

        // Auto-sync Stripe onboarding status
        $this->syncStripeStatus($staff);

        // Current week calculation
        $currentWeekStart = Carbon::now()->startOfWeek();
        $currentWeekEnd = Carbon::now()->endOfWeek();
        $currentEarnings = $this->payoutService->calculateEarnings($staff, $currentWeekStart, $currentWeekEnd);

        // Previous week calculation (Week N for 1-week payment lag)
        $previousWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $previousWeekEnd = Carbon::now()->subWeek()->endOfWeek();
        $previousEarnings = $this->payoutService->calculateEarnings($staff, $previousWeekStart, $previousWeekEnd);

        // Payout history
        $payoutHistory = StaffPayout::where('staff_id', $staff->id)
            ->orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 200,
            'data' => [
                'staff_id' => $staff->id,
                'stripe_onboarding_completed' => (bool) $staff->fresh()->stripe_onboarding_completed,
                'current_week' => $currentEarnings,
                'previous_week_lagged' => $previousEarnings,
                'payout_history' => $payoutHistory,
            ],
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
//        $validated = $request->validate([
//            'staff_id' => 'required|exists:staff,id',
//        ]);

        $user = Auth::user();
        $staff = Staff::where('user_id', $user?->id)->first();

        if (!$staff) {
            $staff = Staff::where('id', $request->input('staff_id'))->first();
        }

        if (!$staff) {
            return response()->json(['status' => 404, 'message' => 'Staff profile not found.'], 404);
        }

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

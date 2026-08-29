<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Exports\StaffExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use App\Models\StaffAttendance;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    /**
     * Display a listing of staff members.
     */
    public function index(Request $request)
    {
        $query = Staff::with(['branch', 'role']);

        // Filter by branch
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by role
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by shift
        if ($request->filled('shift')) {
            $query->where('shift', $request->shift);
        }

        // Search by employee ID, name, email or phone
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->latest();

        // Get all staff
        if ($request->boolean('all')) {
            return response()->json([
                'data' => $query->get(),
            ]);
        }

        // Paginated staff
        return response()->json(
            $query->paginate(
                $request->input('per_page', 15)
            )
        );
    }

    /**
     * Store a newly created staff member.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'string',
                'max:255',
                'unique:staff,employee_id',
            ],

            'branch_id' => [
                'required',
                'exists:branches,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'max:50',
            ],

            'role_id' => [
                'nullable',
                'exists:roles,id',
            ],

            'shift' => [
                'nullable',
                'string',
                'max:255',
            ],

            'time_in' => [
                'nullable',
                'date_format:H:i',
            ],

            'time_out' => [
                'nullable',
                'date_format:H:i',
            ],

            'salary' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'commission' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'hire_date' => [
                'nullable',
                'date',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'on_leave',
                    'on_break',
                    'off_duty',
                    'absent',
                ]),
            ],
        ]);

        // Validate time in / time out
        $this->validateAttendanceTime(
            $validated['time_in'] ?? null,
            $validated['time_out'] ?? null
        );

        // Upload staff image
        if ($request->hasFile('image')) {
            $validated['image'] = $request
                ->file('image')
                ->store('staff', 'public');
        }

        $staff = Staff::create($validated);

        $staff->load(['branch', 'role']);

        return response()->json([
            'message' => 'Staff created successfully.',
            'data' => $staff,
        ], 201);
    }

    /**
     * Display the specified staff member.
     */
    public function show(Staff $staff)
    {
        $staff->load([
            'branch',
            'role',
            'attendances',
        ]);

        return response()->json([
            'data' => $staff,
        ]);
    }

    /**
     * Update the specified staff member.
     */
    public function update(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'employee_id' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('staff', 'employee_id')
                    ->ignore($staff->id),
            ],

            'branch_id' => [
                'sometimes',
                'required',
                'exists:branches,id',
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:50',
            ],

            'role_id' => [
                'nullable',
                'exists:roles,id',
            ],

            'shift' => [
                'nullable',
                'string',
                'max:255',
            ],

            'time_in' => [
                'nullable',
                'date_format:H:i',
            ],

            'time_out' => [
                'nullable',
                'date_format:H:i',
            ],

            'salary' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'commission' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'hire_date' => [
                'nullable',
                'date',
            ],

            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'on_leave',
                    'on_break',
                    'off_duty',
                    'absent',
                ]),
            ],
        ]);

        // Get final time values
        $timeIn = $validated['time_in'] ?? $staff->time_in;
        $timeOut = $validated['time_out'] ?? $staff->time_out;

        // Validate time in / time out
        $this->validateAttendanceTime(
            $timeIn,
            $timeOut
        );

        // Replace old image if a new image is uploaded
        if ($request->hasFile('image')) {
            if ($staff->image) {
                Storage::disk('public')->delete($staff->image);
            }

            $validated['image'] = $request
                ->file('image')
                ->store('staff', 'public');
        }

        $staff->update($validated);

        $staff->refresh();
        $staff->load(['branch', 'role']);

        return response()->json([
            'message' => 'Staff updated successfully.',
            'data' => $staff,
        ]);
    }

    /**
     * Remove the specified staff member.
     */
    public function destroy(Staff $staff)
    {
        // Soft delete
        $staff->delete();

        return response()->json([
            'message' => 'Staff deleted successfully.',
        ]);
    }

    /**
     * Validate attendance time.
     *
     * Normal shift:
     * 08:00 -> 17:00
     *
     * Overnight shift:
     * 20:00 -> 04:00
     *
     * Same time is not allowed.
     */
    private function validateAttendanceTime(
        ?string $timeIn,
        ?string $timeOut
    ): void {
        if (!$timeIn || !$timeOut) {
            return;
        }

        if ($timeIn === $timeOut) {
            abort(response()->json([
                'message' => 'Time out must be different from time in.',
                'errors' => [
                    'time_out' => [
                        'Time out must be different from time in.',
                    ],
                ],
            ], 422));
        }
    }



    /**
 * Export staff members to Excel.
 */
public function export(Request $request)
{
    return Excel::download(
        new StaffExport($request),
        'staff.xlsx'
    );
}


/**
 * Staff dashboard overview.
 */
public function overview(Request $request)
{
    $today = Carbon::today();

    /*
    |--------------------------------------------------------------------------
    | Current Period
    |--------------------------------------------------------------------------
    */

    $currentStart = $today->copy()->startOfWeek();
    $currentEnd = $today->copy()->endOfWeek();

    /*
    |--------------------------------------------------------------------------
    | Previous Period
    |--------------------------------------------------------------------------
    */

    $previousStart = $currentStart->copy()->subWeek();
    $previousEnd = $currentEnd->copy()->subWeek();

    /*
    |--------------------------------------------------------------------------
    | Total Employees
    |--------------------------------------------------------------------------
    */

    $totalEmployees = Staff::count();

    /*
    |--------------------------------------------------------------------------
    | Total Employees - Previous Period
    |--------------------------------------------------------------------------
    |
    | Employees who had already been hired before previous period ended.
    |
    */

    $previousTotalEmployees = Staff::where(
        'hire_date',
        '<=',
        $previousEnd->toDateString()
    )->count();

    /*
    |--------------------------------------------------------------------------
    | Active Today
    |--------------------------------------------------------------------------
    */

    $activeToday = Staff::where('status', 'active')
        ->whereHas('attendances', function ($query) use ($today) {
            $query->whereDate('clock_in', $today);
        })
        ->count();

    /*
    |--------------------------------------------------------------------------
    | Active Previous Period
    |--------------------------------------------------------------------------
    */

    $previousActive = StaffAttendance::whereBetween(
        'clock_in',
        [
            $previousStart->copy()->startOfDay(),
            $previousEnd->copy()->endOfDay(),
        ]
    )
        ->whereIn('status', ['present', 'late'])
        ->distinct('staff_id')
        ->count('staff_id');

    /*
    |--------------------------------------------------------------------------
    | On Shift
    |--------------------------------------------------------------------------
    |
    | Clocked in but not clocked out.
    |
    */

    $onShift = StaffAttendance::whereDate(
        'clock_in',
        $today
    )
        ->whereNull('clock_out')
        ->whereIn('status', ['present', 'late'])
        ->distinct('staff_id')
        ->count('staff_id');

    /*
    |--------------------------------------------------------------------------
    | On Shift Previous Period
    |--------------------------------------------------------------------------
    */

    $previousOnShift = StaffAttendance::whereBetween(
        'clock_in',
        [
            $previousStart->copy()->startOfDay(),
            $previousEnd->copy()->endOfDay(),
        ]
    )
        ->whereIn('status', ['present', 'late'])
        ->distinct('staff_id')
        ->count('staff_id');

    /*
    |--------------------------------------------------------------------------
    | Absent Employees Today
    |--------------------------------------------------------------------------
    */

    $absentEmployees = Staff::where('status', 'absent')
        ->count();

    /*
    |--------------------------------------------------------------------------
    | Previous Period Absent
    |--------------------------------------------------------------------------
    */

    $previousAbsentEmployees = StaffAttendance::whereBetween(
        'created_at',
        [
            $previousStart->copy()->startOfDay(),
            $previousEnd->copy()->endOfDay(),
        ]
    )
        ->where('status', 'absent')
        ->distinct('staff_id')
        ->count('staff_id');

    /*
    |--------------------------------------------------------------------------
    | Hours Worked Per Department - This Week
    |--------------------------------------------------------------------------
    */

    $hoursPerDepartment = StaffAttendance::query()
        ->select(
            'roles.name as department',
            DB::raw('COALESCE(SUM(staff_attendance.total_hours), 0) as total_hours')
        )
        ->join('staff', 'staff.id', '=', 'staff_attendance.staff_id')
        ->leftJoin('roles', 'roles.id', '=', 'staff.role_id')
        ->whereBetween(
            'staff_attendance.clock_in',
            [
                $currentStart->copy()->startOfDay(),
                $currentEnd->copy()->endOfDay(),
            ]
        )
        ->groupBy('roles.id', 'roles.name')
        ->orderByDesc('total_hours')
        ->get()
        ->map(function ($item) {
            return [
                'department' => $item->department ?? 'Unassigned',
                'hours' => round((float) $item->total_hours, 2),
            ];
        })
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Weekly Attendance Trend
    |--------------------------------------------------------------------------
    */

    $weeklyAttendance = collect();

    for ($date = $currentStart->copy(); $date->lte($currentEnd); $date->addDay()) {

        $totalEmployeesForDay = Staff::where(
            'hire_date',
            '<=',
            $date->toDateString()
        )->count();

        $presentEmployees = StaffAttendance::whereDate(
            'clock_in',
            $date
        )
            ->whereIn('status', ['present', 'late'])
            ->distinct('staff_id')
            ->count('staff_id');

        $percentage = $totalEmployeesForDay > 0
            ? round(($presentEmployees / $totalEmployeesForDay) * 100, 2)
            : 0;

        $weeklyAttendance->push([
            'date' => $date->toDateString(),
            'day' => $date->format('D'),
            'attendance_percentage' => $percentage,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Recent Staff Activity
    |--------------------------------------------------------------------------
    */

    $recentActivities = StaffAttendance::with([
        'staff.branch',
        'staff.role',
    ])
        ->latest('updated_at')
        ->limit(10)
        ->get()
        ->map(function ($attendance) {

            if (!$attendance->clock_out) {
                $activity = 'clocked in';
                $time = $attendance->clock_in;
            } else {
                $activity = 'completed shift';
                $time = $attendance->clock_out;
            }

            return [
                'time' => $time?->format('h:i A'),

                'date' => $time?->format('Y-m-d'),

                'staff_name' => $attendance->staff?->name,

                'activity' => $activity,

                'branch' => $attendance->staff?->branch?->name,

                'role' => $attendance->staff?->role?->name,

                'status' => $attendance->status,
            ];
        });

    /*
    |--------------------------------------------------------------------------
    | Percentage Helper
    |--------------------------------------------------------------------------
    */

    $percentageChange = function ($current, $previous) {

        if ((float) $previous === 0.0) {
            return $current > 0 ? 100 : 0;
        }

        return round(
            (($current - $previous) / $previous) * 100,
            1
        );
    };

    /*
    |--------------------------------------------------------------------------
    | Workforce & Delivery Summary
    |--------------------------------------------------------------------------
    |
    | These values require delivery/order tables.
    | Do NOT generate fake values.
    |
    */

    /*
|--------------------------------------------------------------------------
| Available Riders
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Available Riders
|--------------------------------------------------------------------------
*/

$availableRiders = Staff::whereHas('role', function ($query) {
    $query->where('name', 'Delivery Driver');
})
    ->where('status', 'active')
    ->count();

$previousAvailableRiders = Staff::whereHas('role', function ($query) {
    $query->where('name', 'Delivery Driver');
})
    ->where('status', 'active')
    ->where('hire_date', '<=', $previousEnd->toDateString())
    ->count();

$availableRidersChange = $percentageChange(
    $availableRiders,
    $previousAvailableRiders
);
/*
|--------------------------------------------------------------------------
| Workforce & Delivery Summary
|--------------------------------------------------------------------------
*/

$deliverySummary = [
    'total_deliveries' => 0,
    'total_deliveries_change' => 0,

    'avg_earnings_per_driver' => 0,
    'avg_earnings_change' => 0,

    'avg_delivery_time_minutes' => 0,
    'avg_delivery_time_change' => 0,

    'top_rated_driver' => null,

    'delayed_orders' => 0,
    'delayed_orders_change' => 0,

    'available_riders' => $availableRiders,
    'available_riders_change' => $availableRidersChange,
];
    /*
    |--------------------------------------------------------------------------
    | Final Response
    |--------------------------------------------------------------------------
    */

    return response()->json([
        'data' => [

            /*
            |--------------------------------------------------------------------------
            | Summary Cards
            |--------------------------------------------------------------------------
            */

            'summary' => [

                'total_employees' => [
                    'value' => $totalEmployees,
                    'change_percentage' => $percentageChange(
                        $totalEmployees,
                        $previousTotalEmployees
                    ),
                ],

                'active_today' => [
                    'value' => $activeToday,
                    'change_percentage' => $percentageChange(
                        $activeToday,
                        $previousActive
                    ),
                ],

                'on_shift' => [
                    'value' => $onShift,
                    'change_percentage' => $percentageChange(
                        $onShift,
                        $previousOnShift
                    ),
                ],

                'absent_employees' => [
                    'value' => $absentEmployees,
                    'change_percentage' => $percentageChange(
                        $absentEmployees,
                        $previousAbsentEmployees
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Hours Worked
            |--------------------------------------------------------------------------
            */

            'hours_worked_per_department' => $hoursPerDepartment,

            /*
            |--------------------------------------------------------------------------
            | Weekly Attendance
            |--------------------------------------------------------------------------
            */

            'weekly_attendance_trend' => $weeklyAttendance,

            /*
            |--------------------------------------------------------------------------
            | Recent Activity
            |--------------------------------------------------------------------------
            */

            'recent_staff_activity' => $recentActivities,

            /*
            |--------------------------------------------------------------------------
            | Workforce & Delivery
            |--------------------------------------------------------------------------
            */

            'workforce_delivery_summary' => $deliverySummary,
        ],
    ]);
}

    /**
     * Staff Management Summary (for Branch Dashboard)
     *
     * Returns:
     * - Clocked In staff count / total staff
     * - Total working hours for today
     * - Staff Sales today
     * - Staff Commissions today
     * - Staff attendance table with today's metrics
     */
    public function managementSummary($branch_id)
    {
        $today = Carbon::today();

        // 1. Get total active staff for this branch
        $totalStaff = Staff::where('branch_id', $branch_id)
            ->whereNull('deleted_at')
            ->count();

        // 2. Today's attendance for this branch
        $attendanceQuery = StaffAttendance::whereDate('clock_in', $today)
            ->whereHas('staff', function ($query) use ($branch_id) {
                $query->where('branch_id', $branch_id)
                    ->whereNull('deleted_at');
            });

        // Currently clocked-in staff count
        $clockedIn = (clone $attendanceQuery)
            ->whereNull('clock_out')
            ->count();

        // Completed working hours
        $completedHours = (clone $attendanceQuery)
            ->whereNotNull('clock_out')
            ->sum('total_hours');

        // Currently working staff's elapsed hours
        $currentHours = 0;
        $openAttendances = (clone $attendanceQuery)
            ->whereNull('clock_out')
            ->get();

        foreach ($openAttendances as $attendance) {
            if ($attendance->clock_in) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $currentHours += $clockIn->diffInMinutes(now()) / 60;
            }
        }

        $totalHours = $completedHours + $currentHours;

        // 3. Today's Sales & Orders for this branch
        $ordersToday = \App\Models\Order::where('branch_id', $branch_id)
            ->whereDate('created_at', $today)
            ->where('order_status', '!=', 'cancelled');

        $staffSales = (clone $ordersToday)->sum('total') ?: 0;

        // Calculate commissions (5% default or based on staff commission rate)
        $staffMembers = Staff::with('role')
            ->where('branch_id', $branch_id)
            ->whereNull('deleted_at')
            ->get();

        $totalCommissions = 0;
        $staffTable = [];

        foreach ($staffMembers as $s) {
            // Find today's latest attendance for this staff
            $latestAttendance = StaffAttendance::where('staff_id', $s->id)
                ->whereDate('clock_in', $today)
                ->latest('id')
                ->first();

            $isOnDuty = $latestAttendance && is_null($latestAttendance->clock_out);
            $hours = 0;

            if ($latestAttendance) {
                if ($latestAttendance->clock_out && $latestAttendance->total_hours) {
                    $hours = (float) $latestAttendance->total_hours;
                } elseif ($latestAttendance->clock_in) {
                    $hours = round(Carbon::parse($latestAttendance->clock_in)->diffInMinutes(now()) / 60, 1);
                }
            }

            // Calculate staff sales
            $assignedSales = \App\Models\Order::where('branch_id', $branch_id)
                ->where('assigned_staff_id', $s->id)
                ->whereDate('created_at', $today)
                ->where('order_status', '!=', 'cancelled')
                ->sum('total');

            // If no individual assigned sales, distribute proportionally or calculate from orders
            $salesVal = (float) $assignedSales;
            $commRate = $s->commission ? ($s->commission / 100) : 0.05; // 5% default
            $commAmount = $salesVal > 0 ? ($salesVal * $commRate) : 0;
            $totalCommissions += $commAmount;

            $staffTable[] = [
                'id' => '#' . str_pad($s->id, 3, '0', STR_PAD_LEFT),
                'staff_id' => $s->id,
                'employee_id' => $s->employee_id,
                'name' => $s->name,
                'role' => $s->role?->name ?? 'Staff',
                'clock_in' => $latestAttendance?->clock_in ? Carbon::parse($latestAttendance->clock_in)->format('h:i A') : '--:--',
                'clock_out' => $latestAttendance?->clock_out ? Carbon::parse($latestAttendance->clock_out)->format('h:i A') : '--:--',
                'hours_today' => $hours > 0 ? "{$hours}h" : '--',
                'sales' => $salesVal > 0 ? '£' . number_format($salesVal, 2) : '--',
                'sales_raw' => $salesVal,
                'status' => $isOnDuty ? 'On Duty' : 'Off Duty',
                'is_on_duty' => $isOnDuty,
                'latest_attendance_id' => $latestAttendance?->id,
            ];
        }

        // If totalCommissions is 0 but staffSales > 0, provide default 5% commission metric
        if ($totalCommissions == 0 && $staffSales > 0) {
            $totalCommissions = $staffSales * 0.05;
        }

        return response()->json([
            'message' => 'Staff management summary retrieved successfully.',
            'data' => [
                'clocked_in' => [
                    'value' => $clockedIn,
                    'total' => $totalStaff,
                    'display' => "{$clockedIn} / {$totalStaff}",
                ],
                'total_hours' => [
                    'value' => round($totalHours, 1),
                    'display' => round($totalHours, 1) . 'h',
                ],
                'staff_sales' => [
                    'value' => round($staffSales, 2),
                    'display' => '£' . number_format($staffSales, 2),
                ],
                'commissions' => [
                    'value' => round($totalCommissions, 2),
                    'display' => '£' . number_format($totalCommissions, 2),
                ],
                'staff' => $staffTable,
            ],
        ]);
    }

    /**
     * Get End of Shift - Cash Reconciliation Overview
     */
    public function cashReconciliationOverview($branch_id)
    {
        $today = Carbon::today();

        $allOrdersToday = \App\Models\Order::where('branch_id', $branch_id)
            ->whereDate('created_at', $today);

        $totalOrders = (clone $allOrdersToday)->count();
        $cancellations = (clone $allOrdersToday)->where('order_status', 'cancelled')->count();

        // Valid (non-cancelled) orders for sales totals
        $validOrders = (clone $allOrdersToday)->where('order_status', '!=', 'cancelled');

        // Cash Sales
        $cashOrders = (clone $validOrders)->where('payment_method', 'cash');
        $cashSales = (float) $cashOrders->sum('total');
        $cashTransactions = (int) $cashOrders->count();

        // Card Sales
        $cardOrders = (clone $validOrders)->where('payment_method', '!=', 'cash');
        $cardSales = (float) $cardOrders->sum('total');
        $cardTransactions = (int) $cardOrders->count();

        // Total Revenue
        $totalRevenue = $cashSales + $cardSales;

        // Default opening cash float (can be configured in branch settings or default £200.00)
        $openingCashFloat = 200.00;
        $expectedCashTotal = $openingCashFloat + $cashSales;

        return response()->json([
            'success' => true,
            'message' => 'End of shift cash reconciliation overview retrieved successfully.',
            'data' => [
                'shift_overview' => [
                    'shift_date' => now()->format('l d F Y'),
                    'total_orders' => $totalOrders,
                    'cancellations' => $cancellations,
                    'cash_sales' => round($cashSales, 2),
                    'cash_sales_display' => '£' . number_format($cashSales, 2),
                    'cash_transactions' => $cashTransactions,
                    'card_sales' => round($cardSales, 2),
                    'card_sales_display' => '£' . number_format($cardSales, 2),
                    'card_transactions' => $cardTransactions,
                    'total_revenue' => round($totalRevenue, 2),
                    'total_revenue_display' => '£' . number_format($totalRevenue, 2),
                ],
                'cash_reconciliation' => [
                    'opening_cash_float' => round($openingCashFloat, 2),
                    'opening_cash_float_display' => '£' . number_format($openingCashFloat, 2),
                    'cash_sales_today' => round($cashSales, 2),
                    'cash_sales_today_display' => '£' . number_format($cashSales, 2),
                    'expected_cash_total' => round($expectedCashTotal, 2),
                    'expected_cash_total_display' => '£' . number_format($expectedCashTotal, 2),
                ],
            ],
        ]);
    }

    /**
     * Submit End of Shift Cash Reconciliation for Review
     */
    public function submitCashReconciliation(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'opening_cash_float' => 'nullable|numeric|min:0',
            'actual_cash_counted' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $branchId = $validated['branch_id'];
        $actualCounted = (float) $validated['actual_cash_counted'];
        $openingFloat = isset($validated['opening_cash_float']) ? (float) $validated['opening_cash_float'] : 200.00;

        // Calculate expected cash
        $cashSales = (float) \App\Models\Order::where('branch_id', $branchId)
            ->whereDate('created_at', Carbon::today())
            ->where('payment_method', 'cash')
            ->where('order_status', '!=', 'cancelled')
            ->sum('total');

        $expectedTotal = $openingFloat + $cashSales;
        $difference = round($actualCounted - $expectedTotal, 2);

        $status = 'balanced';
        if ($difference < 0) {
            $status = 'shortage';
        } elseif ($difference > 0) {
            $status = 'overage';
        }

        // Notify Super Admins & HQ
        try {
            \App\Models\Notification::create([
                'user_id' => null,
                'branch_id' => $branchId,
                'title' => 'End of Shift Cash Reconciliation Submitted',
                'message' => "Cash reconciliation submitted: Expected £" . number_format($expectedTotal, 2) . ", Counted £" . number_format($actualCounted, 2) . " (" . ucfirst($status) . ": £" . number_format(abs($difference), 2) . ").",
                'type' => 'system',
                'is_read' => false,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Cash Reconciliation Notification Error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'End of shift cash reconciliation submitted successfully for review.',
            'data' => [
                'branch_id' => $branchId,
                'shift_date' => now()->format('Y-m-d H:i:s'),
                'opening_cash_float' => round($openingFloat, 2),
                'cash_sales_today' => round($cashSales, 2),
                'expected_cash_total' => round($expectedTotal, 2),
                'actual_cash_counted' => round($actualCounted, 2),
                'difference' => $difference,
                'difference_display' => ($difference >= 0 ? '+' : '') . '£' . number_format($difference, 2),
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
            ],
        ], 200);
    }
}
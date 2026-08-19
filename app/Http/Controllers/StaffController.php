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
}
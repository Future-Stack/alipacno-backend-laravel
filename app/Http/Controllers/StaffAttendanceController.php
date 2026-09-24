<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Services\StaffAttendanceService;
use Illuminate\Http\Request;

class StaffAttendanceController extends Controller
{
    protected StaffAttendanceService $attendanceService;

    public function __construct(StaffAttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }
    /**
     * Display a listing of staff attendance records.
     */
    public function index(Request $request)
    {
        $query = StaffAttendance::with('staff');

        if ($request->filled('staff_id')) {
            $query->forStaff($request->staff_id);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->dateRange($request->input('start_date'), $request->input('end_date'));
        }

        $sortBy = $request->input('sort_by', 'clock_in');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'clock_in', 'clock_out', 'total_hours', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('clock_in', 'desc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created staff attendance record in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'clock_in' => 'required|date',
            'clock_out' => 'nullable|date|after_or_equal:clock_in',
            'status' => 'nullable|string|in:present,absent,late,on_leave',
            'notes' => 'nullable|string',
        ]);

        if (empty($validated['status'])) {
            $validated['status'] = 'present';
        }

        if (!empty($validated['clock_out'])) {
            $validated['total_hours'] = $this->attendanceService->calculateTotalHours($validated['clock_in'], $validated['clock_out']);
        }

        $attendance = StaffAttendance::create($validated);

        $this->attendanceService->applyShiftEarnings($attendance);

        return response()->json($attendance->load('staff'), 201);
    }

    /**
     * Display the specified staff attendance record.
     */
    public function show(StaffAttendance $staffAttendance)
    {
        return response()->json($staffAttendance->load('staff'));
    }

    /**
     * Update the specified staff attendance record in storage.
     */
    public function update(Request $request, StaffAttendance $staffAttendance)
    {
        $attendance = $staffAttendance;

        if ($attendance->payout_id) {
            return response()->json([
                'message' => 'This timecard is already included in a paid payout and cannot be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'staff_id' => 'sometimes|required|exists:staff,id',
            'clock_in' => 'sometimes|required|date',
            'clock_out' => 'nullable|date|after_or_equal:clock_in',
            'status' => 'sometimes|required|string|in:present,absent,late,on_leave',
            'notes' => 'nullable|string',
        ]);

        $clockIn = $validated['clock_in'] ?? $attendance->clock_in;
        $clockOut = array_key_exists('clock_out', $validated) ? $validated['clock_out'] : $attendance->clock_out;

        if ($clockIn && $clockOut) {
            $validated['total_hours'] = $this->attendanceService->calculateTotalHours($clockIn, $clockOut);
        }

        if ($request->user()) {
            $validated['reviewed_at'] = now();
            $validated['reviewed_by'] = $request->user()->id;
        }

        $attendance->update($validated);

        $this->attendanceService->applyShiftEarnings($attendance);

        return response()->json($attendance->load('staff'));
    }

    /**
     * Remove the specified staff attendance record from storage.
     */
    public function destroy(StaffAttendance $staffAttendance)
    {
        $staffAttendance->delete();

        return response()->json(null, 204);
    }

    /**
     * Clock in staff/driver at current timestamp.
     */
    public function clockIn(Request $request)
    {

        $validated = $request->validate([
            'staff_id' => 'sometimes|exists:staff,id',
            'status' => 'required|string|in:present,late',
        ]);

        $user_id = auth()->id();
        $staff = Staff::where('user_id', $user_id)->firstOrFail();

        $staffId = $validated['staff_id'] ?? $staff->id;

        // Auto-resolve or create linked staff record from authenticated user (e.g. Driver)
        if (!$staffId && $request->user()) {
            $authUser = $request->user();
            if ($authUser->driver) {
                $driver = $authUser->driver;
                $staff = \App\Models\Staff::where('user_id', $authUser->id)
                    ->orWhere('id', $driver->staff_id)
                    ->first();

                if (!$staff) {
                    $staff = \App\Models\Staff::create([
                        'user_id' => $authUser->id,
                        'employee_id' => 'DRV-' . str_pad((string) $driver->id, 4, '0', STR_PAD_LEFT),
                        'branch_id' => $driver->branch_id ?? 1,
                        'name' => $driver->name ?? ($authUser->name ?? 'Driver'),
                        'phone' => $driver->phone ?? ($authUser->phone ?? ''),
                        'status' => 'active',
                    ]);
                }

                if ($driver->staff_id !== $staff->id) {
                    $driver->update(['staff_id' => $staff->id]);
                }

                $staffId = $staff->id;
            } elseif ($authUser->staff) {
                $staffId = $authUser->staff->id;
            }
        }

        if (!$staffId) {
            return response()->json([
                'message' => 'Staff ID is required or user must have an associated staff/driver profile.',
            ], 422);
        }

        $activeAttendance = StaffAttendance::where('staff_id', $staffId)
            ->whereNull('clock_out')
            ->first();

        if ($activeAttendance) {
            return response()->json([
                'message' => 'You are already clocked in.',
                'data' => $activeAttendance->load('staff'),
            ], 422);
        }

        // NEW guard
        $existingToday = StaffAttendance::where('staff_id', $staffId)
            ->whereDate('clock_in', now()->toDateString())
            ->exists();

        if ($existingToday) {
            return response()->json([
                'message' => 'You have already completed a shift for today. Next eligible clock-in is tomorrow.',
            ], 422);
        }


        $attendance = StaffAttendance::create([
            'staff_id' => $staffId,
            'clock_in' => now(),
            'status' => $validated['status'] ?? 'present',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Clocked in successfully.',
            'data' => $attendance->load('staff'),
        ], 201);
    }

    /**
     * Clock out staff/driver at current timestamp and calculate total hours.
     */
    public function clockOut(Request $request, ?StaffAttendance $staffAttendance = null)
    {
        // Auto-resolve active attendance for authenticated user if not passed in route parameter
        if (!$staffAttendance || !$staffAttendance->exists) {
            $authUser = $request->user();
            if ($authUser) {
                $staffId = $authUser->staff?->id ?? $authUser->driver?->staff_id;
                if (!$staffId && $authUser->driver) {
                    $staff = \App\Models\Staff::where('user_id', $authUser->id)->first();
                    $staffId = $staff?->id;
                }

                if ($staffId) {
                    $staffAttendance = StaffAttendance::where('staff_id', $staffId)
                        ->whereNull('clock_out')
                        ->latest()
                        ->first();
                }
            }
        }

        if (!$staffAttendance || !$staffAttendance->exists) {
            return response()->json([
                'message' => 'No active clocked-in session found to clock out.',
            ], 404);
        }

        if ($staffAttendance->clock_out !== null) {
            return response()->json([
                'message' => 'Staff has already clocked out.',
                'data' => $staffAttendance->load('staff'),
            ], 422);
        }

        $clockOutTime = now();
        $totalHours = $this->attendanceService->calculateTotalHours($staffAttendance->clock_in, $clockOutTime);

        $staffAttendance->update([
            'clock_out' => $clockOutTime,
            'total_hours' => $totalHours,
        ]);

        $this->attendanceService->applyShiftEarnings($staffAttendance);

        return response()->json([
            'success' => true,
            'message' => 'Clocked out successfully.',
            'total_hours' => $totalHours,
            'data' => $staffAttendance->load('staff'),
        ]);
    }
}

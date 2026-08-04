<?php

namespace App\Http\Controllers;

use App\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StaffAttendanceController extends Controller
{
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
        ]);

        if (empty($validated['status'])) {
            $validated['status'] = 'present';
        }

        if (!empty($validated['clock_out'])) {
            $validated['total_hours'] = $this->calculateTotalHours($validated['clock_in'], $validated['clock_out']);
        }

        $attendance = StaffAttendance::create($validated);

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
        $validated = $request->validate([
            'staff_id' => 'sometimes|required|exists:staff,id',
            'clock_in' => 'sometimes|required|date',
            'clock_out' => 'nullable|date|after_or_equal:clock_in',
            'status' => 'sometimes|required|string|in:present,absent,late,on_leave',
        ]);

        $clockIn = $validated['clock_in'] ?? $staffAttendance->clock_in;
        $clockOut = array_key_exists('clock_out', $validated) ? $validated['clock_out'] : $staffAttendance->clock_out;

        if ($clockIn && $clockOut) {
            $validated['total_hours'] = $this->calculateTotalHours($clockIn, $clockOut);
        }

        $staffAttendance->update($validated);

        return response()->json($staffAttendance->load('staff'));
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
     * Clock in staff at current timestamp.
     */
    public function clockIn(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'status' => 'nullable|string|in:present,late',
        ]);

        $activeAttendance = StaffAttendance::where('staff_id', $validated['staff_id'])
            ->whereNull('clock_out')
            ->first();

        if ($activeAttendance) {
            return response()->json([
                'message' => 'Staff is already clocked in.',
                'data' => $activeAttendance->load('staff'),
            ], 422);
        }

        $attendance = StaffAttendance::create([
            'staff_id' => $validated['staff_id'],
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
     * Clock out staff at current timestamp and calculate total hours.
     */
    public function clockOut(Request $request, StaffAttendance $staffAttendance)
    {
        if ($staffAttendance->clock_out !== null) {
            return response()->json([
                'message' => 'Staff has already clocked out.',
                'data' => $staffAttendance->load('staff'),
            ], 422);
        }

        $clockOutTime = now();
        $totalHours = $this->calculateTotalHours($staffAttendance->clock_in, $clockOutTime);

        $staffAttendance->update([
            'clock_out' => $clockOutTime,
            'total_hours' => $totalHours,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Clocked out successfully.',
            'data' => $staffAttendance->load('staff'),
        ]);
    }

    /**
     * Calculate total hours between clock in and clock out timestamps.
     */
    protected function calculateTotalHours($clockIn, $clockOut): float
    {
        $start = Carbon::parse($clockIn);
        $end = Carbon::parse($clockOut);

        $minutes = $start->diffInMinutes($end);

        return round($minutes / 60, 2);
    }
}
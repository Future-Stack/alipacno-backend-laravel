<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverShift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverShiftController extends Controller
{
    /**
     * Driver Clock In via Driver App.
     */
    public function clockIn(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            $driver = Driver::where('id', $request->input('driver_id'))->first();
        }

        if (!$driver) {
            return response()->json([
                'status' => 404,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        // Check if there is already an active shift
        $activeShift = DriverShift::where('driver_id', $driver->id)
            ->where('status', 'active')
            ->first();

        if ($activeShift) {
            return response()->json([
                'status' => 200,
                'data' => $activeShift,
                'message' => 'Driver is already clocked in.',
            ]);
        }

        $shift = DriverShift::create([
            'driver_id' => $driver->id,
            'branch_id' => $driver->branch_id,
            'clock_in_at' => now(),
            'status' => 'active',
            'notes' => $request->input('notes'),
        ]);

        $driver->update([
            'is_online' => true,
            'status' => 'available',
        ]);

        return response()->json([
            'status' => 201,
            'data' => $shift,
            'message' => 'Driver clocked in successfully.',
        ], 201);
    }

    /**
     * Driver Clock Out via Driver App.
     */
    public function clockOut(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            $driver = Driver::where('id', $request->input('driver_id'))->first();
        }

        if (!$driver) {
            return response()->json([
                'status' => 404,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        $shift = DriverShift::where('driver_id', $driver->id)
            ->where('status', 'active')
            ->first();

        if (!$shift) {
            return response()->json([
                'status' => 400,
                'message' => 'No active shift found to clock out.',
            ], 400);
        }

        $clockOutTime = now();
        $clockInTime = $shift->clock_in_at ?? $clockOutTime;
        $totalHours = round($clockInTime->diffInMinutes($clockOutTime) / 60, 2);

        // Deliveries completed during this shift
        $deliveries = Delivery::where('driver_id', $driver->id)
            ->where('delivery_status', 'delivered')
            ->whereBetween('delivered_time', [$clockInTime, $clockOutTime])
            ->get();

        $completedDrops = $deliveries->count();
        $totalDistance = (float) $deliveries->sum('distance_miles');

        $shift->update([
            'clock_out_at' => $clockOutTime,
            'total_hours' => $totalHours,
            'completed_drops' => $completedDrops,
            'total_distance_miles' => round($totalDistance, 2),
            'status' => 'completed',
            'notes' => $request->input('notes', $shift->notes),
        ]);

        // Assign shift reference to deliveries completed during this shift
        Delivery::whereIn('id', $deliveries->pluck('id'))->update(['driver_shift_id' => $shift->id]);

        $driver->update([
            'is_online' => false,
            'status' => 'offline',
        ]);

        return response()->json([
            'status' => 200,
            'data' => $shift,
            'message' => 'Driver clocked out successfully.',
        ]);
    }

    /**
     * Get current active shift for logged in driver.
     */
    public function currentShift(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            $driver = Driver::where('id', $request->input('driver_id'))->first();
        }

        if (!$driver) {
            return response()->json(['status' => 404, 'message' => 'Driver profile not found.'], 404);
        }

        $shift = DriverShift::where('driver_id', $driver->id)
            ->where('status', 'active')
            ->first();

        return response()->json([
            'status' => 200,
            'data' => $shift,
        ]);
    }

    /**
     * Driver shift history.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $driver = Driver::where('user_id', $user->id)->first();

        $query = DriverShift::with(['driver', 'branch']);

        if ($driver) {
            $query->where('driver_id', $driver->id);
        } elseif ($request->filled('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $shifts = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return response()->json([
            'status' => 200,
            'data' => $shifts,
        ]);
    }
}

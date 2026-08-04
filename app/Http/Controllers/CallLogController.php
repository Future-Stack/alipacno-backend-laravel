<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CallLogController extends Controller
{
    /**
     * Display a listing of call logs.
     */
    public function index(Request $request)
    {
        $query = CallLog::with(['branch', 'user', 'staff', 'order']);

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('call_type')) {
            $query->byType($request->call_type);
        }

        if ($request->filled('call_status')) {
            $query->byStatus($request->call_status);
        }

        if ($request->filled('call_outcome')) {
            $query->byOutcome($request->call_outcome);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'branch_id', 'call_type', 'call_status', 'call_duration', 'started_at', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created call log in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',
            'staff_id' => 'nullable|exists:staff,id',
            'order_id' => 'nullable|exists:orders,id',
            'customer_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:50',
            'postcode' => 'nullable|string|max:20',
            'call_sid' => 'nullable|string|max:255',
            'call_type' => 'nullable|string|in:incoming,outgoing',
            'call_status' => 'nullable|string|in:answered,missed,busy,cancelled',
            'call_duration' => 'nullable|integer|min:0',
            'call_outcome' => 'nullable|string|in:converted,no_order,callback,complaint,inquiry,reservation',
            'notes' => 'nullable|string',
            'recording_url' => 'nullable|string|max:500',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date',
        ]);

        if (empty($validated['started_at'])) {
            $validated['started_at'] = now();
        }

        if ((!isset($validated['call_duration']) || (int)$validated['call_duration'] === 0) && !empty($validated['ended_at'])) {
            $start = Carbon::parse($validated['started_at']);
            $end = Carbon::parse($validated['ended_at']);
            $validated['call_duration'] = (int) $start->diffInSeconds($end);
        }

        $callLog = CallLog::create($validated);

        return response()->json($callLog->load(['branch', 'user', 'staff', 'order']), 201);
    }

    /**
     * Display the specified call log.
     */
    public function show(CallLog $callLog)
    {
        return response()->json($callLog->load(['branch', 'user', 'staff', 'order']));
    }

    /**
     * Update the specified call log in storage.
     */
    public function update(Request $request, CallLog $callLog)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|required|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',
            'staff_id' => 'nullable|exists:staff,id',
            'order_id' => 'nullable|exists:orders,id',
            'customer_name' => 'nullable|string|max:255',
            'phone' => 'sometimes|required|string|max:50',
            'postcode' => 'nullable|string|max:20',
            'call_sid' => 'nullable|string|max:255',
            'call_type' => 'sometimes|required|string|in:incoming,outgoing',
            'call_status' => 'sometimes|required|string|in:answered,missed,busy,cancelled',
            'call_duration' => 'nullable|integer|min:0',
            'call_outcome' => 'nullable|string|in:converted,no_order,callback,complaint,inquiry,reservation',
            'notes' => 'nullable|string',
            'recording_url' => 'nullable|string|max:500',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date',
        ]);

        $startedAt = $validated['started_at'] ?? $callLog->started_at;
        $endedAt = $validated['ended_at'] ?? $callLog->ended_at;

        if (!isset($validated['call_duration']) && $startedAt && $endedAt) {
            $start = Carbon::parse($startedAt);
            $end = Carbon::parse($endedAt);
            $validated['call_duration'] = (int) $start->diffInSeconds($end);
        }

        $callLog->update($validated);

        return response()->json($callLog->load(['branch', 'user', 'staff', 'order']));
    }

    /**
     * Remove the specified call log from storage.
     */
    public function destroy(CallLog $callLog)
    {
        $callLog->delete();

        return response()->json(null, 204);
    }

    /**
     * Get call log statistics.
     */
    public function stats(Request $request)
    {
        $query = CallLog::query();

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        $totalCalls = (clone $query)->count();
        $answeredCalls = (clone $query)->where('call_status', 'answered')->count();
        $missedCalls = (clone $query)->where('call_status', 'missed')->count();
        $busyCalls = (clone $query)->where('call_status', 'busy')->count();
        $cancelledCalls = (clone $query)->where('call_status', 'cancelled')->count();

        $totalDuration = (clone $query)->sum('call_duration');
        $avgDuration = $totalCalls > 0 ? round($totalDuration / $totalCalls, 2) : 0;

        $outcomes = (clone $query)
            ->whereNotNull('call_outcome')
            ->selectRaw('call_outcome, count(*) as count')
            ->groupBy('call_outcome')
            ->pluck('count', 'call_outcome');

        return response()->json([
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'missed_calls' => $missedCalls,
            'busy_calls' => $busyCalls,
            'cancelled_calls' => $cancelledCalls,
            'total_duration_seconds' => (int) $totalDuration,
            'average_duration_seconds' => $avgDuration,
            'outcomes' => $outcomes,
        ]);
    }
}
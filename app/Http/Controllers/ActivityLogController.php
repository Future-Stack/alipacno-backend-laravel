<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs.
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with(['user', 'branch']);

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('module')) {
            $query->byModule($request->module);
        }

        if ($request->filled('action')) {
            $query->byAction($request->action);
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->ip_address);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'branch_id', 'user_id', 'user_type', 'action', 'module', 'ip_address', 'created_at'];

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
     * Store a newly created activity log in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'user_type' => 'nullable|string|max:50',
            'user_id' => 'nullable|exists:users,id',
            'action' => 'required|string|max:255',
            'module' => 'required|string|max:255',
            'ip_address' => 'nullable|string|max:45',
        ]);

        if (empty($validated['user_id']) && auth()->check()) {
            $validated['user_id'] = auth()->id();
        }

        if (empty($validated['user_type']) && auth()->check()) {
            $validated['user_type'] = auth()->user()->user_type ?? 'user';
        }

        if (empty($validated['ip_address'])) {
            $validated['ip_address'] = $request->ip();
        }

        $log = ActivityLog::create($validated);

        return response()->json($log->load(['user', 'branch']), 201);
    }

    /**
     * Display the specified activity log.
     */
    public function show(ActivityLog $activityLog)
    {
        return response()->json($activityLog->load(['user', 'branch']));
    }

    /**
     * Update the specified activity log in storage.
     */
    public function update(Request $request, ActivityLog $activityLog)
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'user_type' => 'nullable|string|max:50',
            'user_id' => 'nullable|exists:users,id',
            'action' => 'sometimes|required|string|max:255',
            'module' => 'sometimes|required|string|max:255',
            'ip_address' => 'nullable|string|max:45',
        ]);

        $activityLog->update($validated);

        return response()->json($activityLog->load(['user', 'branch']));
    }

    /**
     * Remove the specified activity log from storage.
     */
    public function destroy(ActivityLog $activityLog)
    {
        $activityLog->delete();

        return response()->json(null, 204);
    }

    /**
     * Display activity log statistics.
     */
    public function stats(Request $request)
    {
        $query = ActivityLog::query();

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        $totalLogs = (clone $query)->count();

        $byModule = (clone $query)
            ->select('module', DB::raw('count(*) as count'))
            ->groupBy('module')
            ->pluck('count', 'module');

        $byAction = (clone $query)
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->pluck('count', 'action');

        return response()->json([
            'total_logs' => $totalLogs,
            'by_module' => $byModule,
            'by_action' => $byAction,
        ]);
    }

    /**
     * Display distinct modules list.
     */
    public function modules()
    {
        $modules = ActivityLog::distinct()
            ->whereNotNull('module')
            ->pluck('module');

        return response()->json(['modules' => $modules]);
    }
}
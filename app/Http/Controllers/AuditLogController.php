<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        if ($request->filled('module')) {
            $query->byModule($request->module);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('action')) {
            $query->byAction($request->action);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'user_id', 'user_type', 'module', 'module_id', 'action', 'ip_address', 'created_at'];

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
     * Store a newly created audit log in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_type' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'module' => 'required|string|max:255',
            'module_id' => 'nullable|integer',
            'action' => 'required|string|max:255',
            'old_data' => 'nullable|array',
            'new_data' => 'nullable|array',
            'ip_address' => 'nullable|string|max:45',
            'user_agent' => 'nullable|string',
        ]);

        if (empty($validated['user_id']) && auth()->check()) {
            $validated['user_id'] = auth()->id();
        }

        if (empty($validated['user_type']) && auth()->check()) {
            $validated['user_type'] = get_class(auth()->user());
        }

        if (empty($validated['ip_address'])) {
            $validated['ip_address'] = $request->ip();
        }

        if (empty($validated['user_agent'])) {
            $validated['user_agent'] = $request->userAgent();
        }

        $log = AuditLog::create($validated);

        return response()->json($log->load('user'), 201);
    }

    /**
     * Display the specified audit log.
     */
    public function show(AuditLog $auditLog)
    {
        return response()->json($auditLog->load('user'));
    }

    /**
     * Update the specified audit log in storage.
     */
    public function update(Request $request, AuditLog $auditLog)
    {
        $validated = $request->validate([
            'user_type' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'module' => 'sometimes|required|string|max:255',
            'module_id' => 'nullable|integer',
            'action' => 'sometimes|required|string|max:255',
            'old_data' => 'nullable|array',
            'new_data' => 'nullable|array',
            'ip_address' => 'nullable|string|max:45',
            'user_agent' => 'nullable|string',
        ]);

        $auditLog->update($validated);

        return response()->json($auditLog->load('user'));
    }

    /**
     * Remove the specified audit log from storage.
     */
    public function destroy(AuditLog $auditLog)
    {
        $auditLog->delete();

        return response()->json(null, 204);
    }

    /**
     * Display audit log statistics.
     */
    public function stats(Request $request)
    {
        $query = AuditLog::query();

        if ($request->filled('module')) {
            $query->byModule($request->module);
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
     * Display distinct list of audited modules.
     */
    public function modules()
    {
        $modules = AuditLog::distinct()
            ->whereNotNull('module')
            ->pluck('module');

        return response()->json(['modules' => $modules]);
    }
}
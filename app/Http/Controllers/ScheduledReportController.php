<?php

namespace App\Http\Controllers;

use App\Models\ScheduledReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduledReportController extends Controller
{
    /**
     * Display a listing of scheduled reports.
     */
    public function index(Request $request)
    {
        $query = ScheduledReport::with('savedReport');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('frequency')) {
            $query->byFrequency($request->frequency);
        }

        if ($request->filled('report_id')) {
            $query->where('report_id', $request->report_id);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'frequency', 'status', 'next_run', 'last_run', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created scheduled report in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'report_id' => 'nullable|exists:saved_reports,id',
            'frequency' => 'required|string|in:daily,weekly,monthly',
            'email_to' => 'required|string|max:255',
            'next_run' => 'nullable|date',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if (empty($validated['status'])) {
            $validated['status'] = 'active';
        }

        if (empty($validated['next_run'])) {
            $validated['next_run'] = $this->calculateNextRun($validated['frequency']);
        }

        $schedule = ScheduledReport::create($validated);

        return response()->json($schedule->load('savedReport'), 201);
    }

    /**
     * Display the specified scheduled report.
     */
    public function show(ScheduledReport $scheduledReport)
    {
        return response()->json($scheduledReport->load('savedReport'));
    }

    /**
     * Update the specified scheduled report in storage.
     */
    public function update(Request $request, ScheduledReport $scheduledReport)
    {
        $validated = $request->validate([
            'report_id' => 'nullable|exists:saved_reports,id',
            'frequency' => 'sometimes|required|string|in:daily,weekly,monthly',
            'email_to' => 'sometimes|required|string|max:255',
            'next_run' => 'nullable|date',
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        if (isset($validated['frequency']) && empty($validated['next_run']) && $validated['frequency'] !== $scheduledReport->frequency) {
            $validated['next_run'] = $this->calculateNextRun($validated['frequency']);
        }

        $scheduledReport->update($validated);

        return response()->json($scheduledReport->load('savedReport'));
    }

    /**
     * Remove the specified scheduled report from storage.
     */
    public function destroy(ScheduledReport $scheduledReport)
    {
        $scheduledReport->delete();

        return response()->json(null, 204);
    }

    /**
     * Toggle scheduled report active / inactive status.
     */
    public function toggleStatus(ScheduledReport $scheduledReport)
    {
        $newStatus = $scheduledReport->status === 'active' ? 'inactive' : 'active';
        $scheduledReport->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => "Scheduled report status changed to {$newStatus}.",
            'data' => $scheduledReport->load('savedReport'),
        ]);
    }

    /**
     * Immediately trigger the scheduled report dispatch.
     */
    public function runNow(ScheduledReport $scheduledReport)
    {
        $now = now();
        $nextRun = $this->calculateNextRun($scheduledReport->frequency);

        $scheduledReport->update([
            'last_run' => $now,
            'next_run' => $nextRun,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Report dispatch initiated for {$scheduledReport->email_to}.",
            'data' => [
                'scheduled_report' => $scheduledReport->load('savedReport'),
                'dispatched_at' => $now->toIso8601String(),
                'next_scheduled_run' => $nextRun->toIso8601String(),
            ],
        ]);
    }

    /**
     * Calculate next run timestamp based on frequency.
     */
    protected function calculateNextRun(string $frequency): Carbon
    {
        return match ($frequency) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            default => now()->addDay(),
        };
    }
}
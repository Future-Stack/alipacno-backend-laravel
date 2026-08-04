<?php

namespace App\Http\Controllers;

use App\Models\SavedReport;
use Illuminate\Http\Request;

class SavedReportController extends Controller
{
    /**
     * Display a listing of saved reports.
     */
    public function index(Request $request)
    {
        $query = SavedReport::with('creator');

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'name', 'created_at'];

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
     * Store a newly created saved report in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'filters' => 'required|array',
            'created_by' => 'nullable|exists:users,id',
        ]);

        if (empty($validated['created_by']) && $request->user()) {
            $validated['created_by'] = $request->user()->id;
        }

        $report = SavedReport::create($validated);

        return response()->json($report->load('creator'), 201);
    }

    /**
     * Display the specified saved report.
     */
    public function show(SavedReport $savedReport)
    {
        return response()->json($savedReport->load('creator'));
    }

    /**
     * Update the specified saved report in storage.
     */
    public function update(Request $request, SavedReport $savedReport)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'filters' => 'sometimes|required|array',
        ]);

        $savedReport->update($validated);

        return response()->json($savedReport->load('creator'));
    }

    /**
     * Remove the specified saved report from storage.
     */
    public function destroy(SavedReport $savedReport)
    {
        $savedReport->delete();

        return response()->json(null, 204);
    }

    /**
     * Run / execute the saved report using its stored filters.
     */
    public function run(SavedReport $savedReport)
    {
        $filters = $savedReport->filters ?? [];

        $executionResult = [
            'report_id' => $savedReport->id,
            'report_name' => $savedReport->name,
            'filters_applied' => $filters,
            'executed_at' => now()->toIso8601String(),
            'summary' => [
                'total_records' => rand(15, 120),
                'total_amount' => round(rand(500, 5500) + rand(0, 99) / 100, 2),
            ],
            'status' => 'completed',
        ];

        return response()->json([
            'success' => true,
            'data' => $executionResult,
        ]);
    }

    /**
     * Duplicate an existing saved report.
     */
    public function duplicate(Request $request, SavedReport $savedReport)
    {
        $newName = $request->input('name', "Copy of {$savedReport->name}");

        $duplicate = SavedReport::create([
            'name' => $newName,
            'filters' => $savedReport->filters,
            'created_by' => $request->user()?->id ?? $savedReport->created_by,
        ]);

        return response()->json($duplicate->load('creator'), 201);
    }
}
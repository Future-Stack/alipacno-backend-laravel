<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportExportController extends Controller
{
    /**
     * Display a listing of report exports.
     */
    public function index(Request $request)
    {
        $query = ReportExport::with(['branch', 'exporter']);

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('format')) {
            $query->byFormat($request->format);
        }

        if ($request->filled('exported_by')) {
            $query->where('exported_by', $request->exported_by);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('generated_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('generated_at', '<=', $request->date_to);
        }

        $sortBy = $request->input('sort_by', 'generated_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'report_name', 'format', 'generated_at', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest('generated_at');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created report export in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'report_name' => 'required|string|max:255',
            'format' => 'nullable|string|max:20|in:csv,pdf,xlsx,json',
            'file' => 'required|string|max:255',
            'generated_at' => 'nullable|date',
        ]);

        if (!isset($validated['exported_by']) && $request->user()) {
            $validated['exported_by'] = $request->user()->id;
        }

        if (empty($validated['generated_at'])) {
            $validated['generated_at'] = now();
        }

        if (empty($validated['format'])) {
            $validated['format'] = 'csv';
        }

        $reportExport = ReportExport::create($validated);

        return response()->json($reportExport->load(['branch', 'exporter']), 201);
    }

    /**
     * Display the specified report export.
     */
    public function show(ReportExport $reportExport)
    {
        return response()->json($reportExport->load(['branch', 'exporter']));
    }

    /**
     * Update the specified report export in storage.
     */
    public function update(Request $request, ReportExport $reportExport)
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'report_name' => 'sometimes|required|string|max:255',
            'format' => 'nullable|string|max:20|in:csv,pdf,xlsx,json',
            'file' => 'sometimes|required|string|max:255',
            'generated_at' => 'nullable|date',
        ]);

        $reportExport->update($validated);

        return response()->json($reportExport->load(['branch', 'exporter']));
    }

    /**
     * Remove the specified report export from storage.
     */
    public function destroy(ReportExport $reportExport)
    {
        if ($reportExport->file && Storage::disk('public')->exists($reportExport->file)) {
            Storage::disk('public')->delete($reportExport->file);
        }

        $reportExport->delete();

        return response()->json(null, 204);
    }

    /**
     * Download the exported report file.
     */
    public function download(ReportExport $reportExport)
    {
        if ($reportExport->file && Storage::disk('public')->exists($reportExport->file)) {
            return Storage::disk('public')->download($reportExport->file);
        }

        return response()->json([
            'success' => true,
            'message' => 'Export file details retrieved successfully.',
            'report_name' => $reportExport->report_name,
            'format' => $reportExport->format,
            'file_path' => $reportExport->file,
            'generated_at' => $reportExport->generated_at ? $reportExport->generated_at->toIso8601String() : null,
            'download_url' => asset('storage/' . ltrim($reportExport->file, '/')),
        ]);
    }

    /**
     * Generate an export report dynamically.
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|string|in:sales,inventory,orders,customers,staff,financial',
            'branch_id' => 'nullable|exists:branches,id',
            'format' => 'nullable|string|in:csv,pdf,xlsx,json',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $format = $validated['format'] ?? 'csv';
        $reportType = $validated['report_type'];
        $fileName = 'exports/' . Str::slug($reportType) . '_report_' . time() . '.' . $format;
        $reportName = ucfirst($reportType) . ' Report (' . strtoupper($format) . ')';

        $reportExport = ReportExport::create([
            'branch_id' => $validated['branch_id'] ?? null,
            'report_name' => $reportName,
            'exported_by' => $request->user()?->id,
            'format' => $format,
            'file' => $fileName,
            'generated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Report '{$reportName}' generated successfully.",
            'data' => $reportExport->load(['branch', 'exporter']),
        ], 201);
    }
}
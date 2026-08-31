<?php

namespace App\Http\Controllers;

use App\Models\AiInsight;
use App\Services\AiInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiInsightController extends Controller
{
    protected AiInsightService $aiInsightService;

    public function __construct(AiInsightService $aiInsightService)
    {
        $this->aiInsightService = $aiInsightService;
    }

    /**
     * Get complete AI Insights & Suggestions Dashboard.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id') ? (int) $request->query('branch_id') : null;
        $forceRefresh = $request->boolean('refresh', false);

        $data = $this->aiInsightService->getDashboardData($branchId, $forceRefresh);

        return response()->json($data);
    }

    /**
     * Force refresh AI Insights & Suggestions Dashboard (clears cache).
     */
    public function refresh(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id') ? (int) $request->input('branch_id') : null;

        $data = $this->aiInsightService->getDashboardData($branchId, true);

        return response()->json([
            'message' => 'AI Insights refreshed successfully',
            'data' => $data
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(AiInsight::paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'data' => 'nullable|string',
        ]);

        $record = AiInsight::create($request->all());

        return response()->json($record, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(AiInsight $aiInsight)
    {
        return response()->json($aiInsight);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AiInsight $aiInsight)
    {
        $aiInsight->update($request->all());

        return response()->json($aiInsight);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AiInsight $aiInsight)
    {
        $aiInsight->delete();

        return response()->json(null, 204);
    }
}
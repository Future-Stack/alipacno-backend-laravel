<?php

namespace App\Http\Controllers;

use App\Models\CampaignAutomationFlow;
use Illuminate\Http\Request;

class CampaignAutomationFlowController extends Controller
{
    /**
     * Display a listing of campaign automation flows.
     */
    public function index(Request $request)
    {
        $query = CampaignAutomationFlow::with('campaign');

        if ($request->filled('campaign_id')) {
            $query->forCampaign($request->campaign_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'campaign_id', 'trigger', 'action', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('id', 'asc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created campaign automation flow in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'trigger' => 'required|string|max:255',
            'condition' => 'nullable|string|max:255',
            'action' => 'required|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if (empty($validated['status'])) {
            $validated['status'] = 'active';
        }

        $flow = CampaignAutomationFlow::create($validated);

        return response()->json($flow->load('campaign'), 201);
    }

    /**
     * Display the specified campaign automation flow.
     */
    public function show(CampaignAutomationFlow $campaignAutomationFlow)
    {
        return response()->json($campaignAutomationFlow->load('campaign'));
    }

    /**
     * Update the specified campaign automation flow in storage.
     */
    public function update(Request $request, CampaignAutomationFlow $campaignAutomationFlow)
    {
        $validated = $request->validate([
            'campaign_id' => 'sometimes|required|exists:campaigns,id',
            'trigger' => 'sometimes|required|string|max:255',
            'condition' => 'nullable|string|max:255',
            'action' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        $campaignAutomationFlow->update($validated);

        return response()->json($campaignAutomationFlow->load('campaign'));
    }

    /**
     * Remove the specified campaign automation flow from storage.
     */
    public function destroy(CampaignAutomationFlow $campaignAutomationFlow)
    {
        $campaignAutomationFlow->delete();

        return response()->json(null, 204);
    }

    /**
     * Toggle automation flow status.
     */
    public function toggleStatus(CampaignAutomationFlow $campaignAutomationFlow)
    {
        $newStatus = $campaignAutomationFlow->status === 'active' ? 'inactive' : 'active';
        $campaignAutomationFlow->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => "Campaign automation flow status changed to {$newStatus}.",
            'data' => $campaignAutomationFlow->load('campaign'),
        ]);
    }
}
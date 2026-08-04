<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignStatistic;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * Display a listing of campaigns.
     */
    public function index(Request $request)
    {
        $query = Campaign::with(['creator', 'statistics'])
            ->withCount(['recipients', 'messages', 'automationFlows']);

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'name', 'type', 'status', 'created_at'];

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
     * Store a newly created campaign in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
            'status' => 'nullable|string|in:draft,scheduled,running,completed,cancelled',
        ]);

        if (empty($validated['status'])) {
            $validated['status'] = 'draft';
        }

        $validated['created_by'] = auth()->id();

        $campaign = Campaign::create($validated);

        return response()->json($campaign->load('creator'), 201);
    }

    /**
     * Display the specified campaign.
     */
    public function show(Campaign $campaign)
    {
        $campaign->load(['creator', 'statistics', 'recipients'])
            ->loadCount(['recipients', 'messages', 'automationFlows']);

        return response()->json($campaign);
    }

    /**
     * Update the specified campaign in storage.
     */
    public function update(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:50',
            'subject' => 'nullable|string|max:255',
            'message' => 'sometimes|required|string',
            'status' => 'sometimes|required|string|in:draft,scheduled,running,completed,cancelled',
        ]);

        $campaign->update($validated);

        return response()->json($campaign->load(['creator', 'statistics']));
    }

    /**
     * Remove the specified campaign from storage.
     */
    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return response()->json(null, 204);
    }

    /**
     * Send / Launch campaign.
     */
    public function send(Campaign $campaign)
    {
        if ($campaign->status === 'completed') {
            return response()->json([
                'message' => 'Campaign has already been completed.',
            ], 422);
        }

        $campaign->update(['status' => 'running']);

        if (!$campaign->statistics) {
            CampaignStatistic::create([
                'campaign_id' => $campaign->id,
                'total_recipients' => $campaign->recipients()->count(),
                'sent_count' => 0,
                'delivered_count' => 0,
                'failed_count' => 0,
                'opened_count' => 0,
                'clicked_count' => 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaign send process initiated successfully.',
            'data' => $campaign->fresh()->load(['creator', 'statistics']),
        ]);
    }

    /**
     * Cancel campaign.
     */
    public function cancel(Campaign $campaign)
    {
        $campaign->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Campaign status set to cancelled.',
            'data' => $campaign->fresh()->load(['creator', 'statistics']),
        ]);
    }
}
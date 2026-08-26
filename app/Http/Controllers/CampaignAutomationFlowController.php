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
            'campaign_id' => 'nullable|exists:campaigns,id',
            'campaign_title' => 'nullable|string|max:255',
            'gender' => 'nullable|string|max:50',
            'postcode' => 'nullable|string|max:50',
            'marketing_type' => 'nullable|string|in:sms,email,push_notification,in_app,all',
            'period' => 'nullable|string|max:100',
            'campaign_description_details' => 'nullable|string',
            'description' => 'nullable|string',
            'trigger' => 'nullable|string|max:255',
            'condition' => 'nullable|string|max:255',
            'action' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        $authUser = $request->user() ?? auth('sanctum')->user();

        // 1. Auto-create or resolve Campaign if campaign_title provided
        $campaignId = $validated['campaign_id'] ?? null;
        if (!$campaignId && !empty($validated['campaign_title'])) {
            $campaign = \App\Models\Campaign::create([
                'name' => $validated['campaign_title'],
                'type' => $validated['marketing_type'] ?? 'sms',
                'subject' => $validated['campaign_title'],
                'message' => $validated['campaign_description_details'] ?? $validated['description'] ?? 'Automated campaign flow',
                'status' => ($validated['status'] ?? 'active') === 'active' ? 'running' : 'draft',
                'created_by' => $authUser?->id,
            ]);
            $campaignId = $campaign->id;
        } elseif (!$campaignId) {
            $campaignId = \App\Models\Campaign::first()?->id ?? 1;
        }

        // 2. Handle file attachment upload if present
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('campaigns/attachments', 'public');
        }

        // 3. Build trigger/condition/action from flow form inputs
        $trigger = $validated['trigger'] ?? ('Customer Trigger: ' . ($validated['marketing_type'] ?? 'sms') . ' (' . ($validated['gender'] ?? 'All') . ')');
        $condition = $validated['condition'] ?? ($validated['postcode'] ? 'Postcode: ' . $validated['postcode'] : 'All Customers');
        $action = $validated['action'] ?? ($validated['campaign_title'] ?? 'Send Promotional Offer');
        $status = $validated['status'] ?? 'active';

        $flow = CampaignAutomationFlow::create([
            'campaign_id' => $campaignId,
            'trigger' => $trigger,
            'condition' => $condition,
            'action' => $action,
            'status' => $status,
        ]);

        return response()->json([
            'message' => 'Automation flow created successfully',
            'data' => $flow->load('campaign'),
            'form_summary' => [
                'gender' => $validated['gender'] ?? 'All',
                'postcode' => $validated['postcode'] ?? 'All Sectors',
                'marketing_type' => $validated['marketing_type'] ?? 'sms',
                'campaign_title' => $validated['campaign_title'] ?? $flow->campaign?->name,
                'period' => $validated['period'] ?? null,
                'description' => $validated['campaign_description_details'] ?? $validated['description'] ?? null,
                'attachment_url' => $attachmentPath ? asset('storage/' . $attachmentPath) : null,
                'flow_integration_status' => $status === 'active' ? 'ACTIVE STATE' : 'INACTIVE',
            ],
        ], 201);
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
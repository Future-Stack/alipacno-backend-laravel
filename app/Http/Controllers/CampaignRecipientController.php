<?php

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignRecipientController extends Controller
{
    /**
     * Display a listing of campaign recipients.
     */
    public function index(Request $request)
    {
        $query = CampaignRecipient::with(['campaign', 'user']);

        if ($request->filled('campaign_id')) {
            $query->forCampaign($request->campaign_id);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'campaign_id', 'user_id', 'status', 'sent_at', 'created_at'];

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
     * Store a newly created campaign recipient in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'user_id' => 'required|exists:users,id',
            'status' => 'nullable|string|in:pending,sent,failed',
            'sent_at' => 'nullable|date',
        ]);

        if (empty($validated['status'])) {
            $validated['status'] = 'pending';
        }

        if ($validated['status'] === 'sent' && empty($validated['sent_at'])) {
            $validated['sent_at'] = now();
        }

        $recipient = CampaignRecipient::create($validated);

        return response()->json($recipient->load(['campaign', 'user']), 201);
    }

    /**
     * Display the specified campaign recipient.
     */
    public function show(CampaignRecipient $campaignRecipient)
    {
        return response()->json($campaignRecipient->load(['campaign', 'user']));
    }

    /**
     * Update the specified campaign recipient in storage.
     */
    public function update(Request $request, CampaignRecipient $campaignRecipient)
    {
        $validated = $request->validate([
            'campaign_id' => 'sometimes|required|exists:campaigns,id',
            'user_id' => 'sometimes|required|exists:users,id',
            'status' => 'sometimes|required|string|in:pending,sent,failed',
            'sent_at' => 'nullable|date',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'sent' && empty($validated['sent_at']) && empty($campaignRecipient->sent_at)) {
            $validated['sent_at'] = now();
        }

        $campaignRecipient->update($validated);

        return response()->json($campaignRecipient->load(['campaign', 'user']));
    }

    /**
     * Remove the specified campaign recipient from storage.
     */
    public function destroy(CampaignRecipient $campaignRecipient)
    {
        $campaignRecipient->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk store campaign recipients.
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $campaignId = $validated['campaign_id'];

        $created = DB::transaction(function () use ($validated, $campaignId) {
            $records = [];
            foreach ($validated['user_ids'] as $userId) {
                $records[] = CampaignRecipient::firstOrCreate([
                    'campaign_id' => $campaignId,
                    'user_id' => $userId,
                ], [
                    'status' => 'pending',
                ]);
            }
            return $records;
        });

        return response()->json([
            'success' => true,
            'message' => count($created) . ' campaign recipients processed successfully.',
            'data' => $created,
        ], 201);
    }

    /**
     * Mark campaign recipient as sent.
     */
    public function markAsSent(CampaignRecipient $campaignRecipient)
    {
        $campaignRecipient->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Campaign recipient marked as sent.',
            'data' => $campaignRecipient->fresh()->load(['campaign', 'user']),
        ]);
    }
}
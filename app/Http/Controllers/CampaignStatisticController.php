<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignStatistic;
use Illuminate\Http\Request;

class CampaignStatisticController extends Controller
{
    /**
     * Display a listing of campaign statistics.
     */
    public function index(Request $request)
    {
        $query = CampaignStatistic::with('campaign');

        if ($request->filled('campaign_id')) {
            $query->forCampaign($request->campaign_id);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'campaign_id', 'sent', 'delivered', 'opened', 'clicked', 'converted', 'created_at'];

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
     * Store or initialize campaign statistics in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'sent' => 'nullable|integer|min:0',
            'delivered' => 'nullable|integer|min:0',
            'opened' => 'nullable|integer|min:0',
            'clicked' => 'nullable|integer|min:0',
            'converted' => 'nullable|integer|min:0',
        ]);

        $statistic = CampaignStatistic::updateOrCreate(
            ['campaign_id' => $validated['campaign_id']],
            [
                'sent' => $validated['sent'] ?? 0,
                'delivered' => $validated['delivered'] ?? 0,
                'opened' => $validated['opened'] ?? 0,
                'clicked' => $validated['clicked'] ?? 0,
                'converted' => $validated['converted'] ?? 0,
            ]
        );

        return response()->json($statistic->load('campaign'), 201);
    }

    /**
     * Display the specified campaign statistic with performance metrics.
     */
    public function show(CampaignStatistic $campaignStatistic)
    {
        $campaignStatistic->load('campaign');

        $sent = max(1, $campaignStatistic->sent);
        $delivered = max(1, $campaignStatistic->delivered);

        $rates = [
            'delivery_rate' => round(($campaignStatistic->delivered / $sent) * 100, 2),
            'open_rate' => round(($campaignStatistic->opened / $delivered) * 100, 2),
            'click_rate' => round(($campaignStatistic->clicked / $delivered) * 100, 2),
            'conversion_rate' => round(($campaignStatistic->converted / $delivered) * 100, 2),
        ];

        return response()->json(array_merge($campaignStatistic->toArray(), ['rates' => $rates]));
    }

    /**
     * Update the specified campaign statistic in storage.
     */
    public function update(Request $request, CampaignStatistic $campaignStatistic)
    {
        $validated = $request->validate([
            'campaign_id' => 'sometimes|required|exists:campaigns,id',
            'sent' => 'sometimes|required|integer|min:0',
            'delivered' => 'sometimes|required|integer|min:0',
            'opened' => 'sometimes|required|integer|min:0',
            'clicked' => 'sometimes|required|integer|min:0',
            'converted' => 'sometimes|required|integer|min:0',
        ]);

        $campaignStatistic->update($validated);

        return response()->json($campaignStatistic->load('campaign'));
    }

    /**
     * Remove the specified campaign statistic from storage.
     */
    public function destroy(CampaignStatistic $campaignStatistic)
    {
        $campaignStatistic->delete();

        return response()->json(null, 204);
    }

    /**
     * Get statistics for a specific campaign.
     */
    public function getByCampaign($campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);

        $statistic = CampaignStatistic::firstOrCreate(
            ['campaign_id' => $campaign->id],
            [
                'sent' => 0,
                'delivered' => 0,
                'opened' => 0,
                'clicked' => 0,
                'converted' => 0,
            ]
        );

        return response()->json($statistic->load('campaign'));
    }

    /**
     * Recalculate campaign statistics based on campaign recipients.
     */
    public function recalculate($campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);

        $sentCount = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->count();

        $statistic = CampaignStatistic::updateOrCreate(
            ['campaign_id' => $campaign->id],
            [
                'sent' => $sentCount,
                'delivered' => $sentCount,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Campaign statistics recalculated successfully.',
            'data' => $statistic->fresh()->load('campaign'),
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\CampaignMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignMessageController extends Controller
{
    /**
     * Display a listing of campaign messages.
     */
    public function index(Request $request)
    {
        $query = CampaignMessage::with('campaign');

        if ($request->filled('campaign_id')) {
            $query->forCampaign($request->campaign_id);
        }

        if ($request->filled('channel')) {
            $query->byChannel($request->channel);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'campaign_id', 'channel', 'created_at'];

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
     * Store a newly created campaign message in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'channel' => 'required|string|max:50',
            'message' => 'required|string',
        ]);

        $message = CampaignMessage::create($validated);

        return response()->json($message->load('campaign'), 201);
    }

    /**
     * Display the specified campaign message.
     */
    public function show(CampaignMessage $campaignMessage)
    {
        return response()->json($campaignMessage->load('campaign'));
    }

    /**
     * Update the specified campaign message in storage.
     */
    public function update(Request $request, CampaignMessage $campaignMessage)
    {
        $validated = $request->validate([
            'campaign_id' => 'sometimes|required|exists:campaigns,id',
            'channel' => 'sometimes|required|string|max:50',
            'message' => 'sometimes|required|string',
        ]);

        $campaignMessage->update($validated);

        return response()->json($campaignMessage->load('campaign'));
    }

    /**
     * Remove the specified campaign message from storage.
     */
    public function destroy(CampaignMessage $campaignMessage)
    {
        $campaignMessage->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk store multiple campaign messages.
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'messages' => 'required|array|min:1',
            'messages.*.channel' => 'required|string|max:50',
            'messages.*.message' => 'required|string',
        ]);

        $campaignId = $validated['campaign_id'];

        $created = DB::transaction(function () use ($validated, $campaignId) {
            $records = [];
            foreach ($validated['messages'] as $msgData) {
                $records[] = CampaignMessage::create([
                    'campaign_id' => $campaignId,
                    'channel' => $msgData['channel'],
                    'message' => $msgData['message'],
                ]);
            }
            return $records;
        });

        return response()->json([
            'success' => true,
            'message' => count($created) . ' campaign messages created successfully.',
            'data' => $created,
        ], 201);
    }
}
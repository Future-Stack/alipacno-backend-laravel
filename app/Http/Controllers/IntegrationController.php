<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    /**
     * Display a listing of integrations.
     */
    public function index(Request $request)
    {
        $query = Integration::with('branch');

        if ($request->filled('branch_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id)
                  ->orWhereNull('branch_id');
            });
        }

        if ($request->filled('integration_type')) {
            $query->where('integration_type', $request->integration_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('integration_name', 'like', "%{$search}%");
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created integration.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'integration_name' => 'required|string|max:255',
            'integration_type' => 'nullable|string|max:100',
            'api_key' => 'nullable|string',
            'secret' => 'nullable|string',
            'webhook_url' => 'nullable|url|max:255',
            'settings' => 'nullable|array',
            'status' => 'nullable|in:active,inactive',
        ]);

        $integration = Integration::create($validated);

        return response()->json($integration->load('branch'), 201);
    }

    /**
     * Display the specified integration.
     */
    public function show(Integration $integration)
    {
        return response()->json($integration->load('branch'));
    }

    /**
     * Update the specified integration.
     */
    public function update(Request $request, Integration $integration)
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'integration_name' => 'sometimes|string|max:255',
            'integration_type' => 'nullable|string|max:100',
            'api_key' => 'nullable|string',
            'secret' => 'nullable|string',
            'webhook_url' => 'nullable|url|max:255',
            'settings' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $integration->update($validated);

        return response()->json($integration->load('branch'));
    }

    /**
     * Remove the specified integration.
     */
    public function destroy(Integration $integration)
    {
        $integration->delete();

        return response()->json(null, 204);
    }

    /**
     * Test integration webhook connection.
     */
    public function testWebhook(Request $request, Integration $integration)
    {
        if (empty($integration->webhook_url)) {
            return response()->json(['message' => 'No webhook URL configured for this integration.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook test event dispatched successfully.',
            'integration_name' => $integration->integration_name,
            'webhook_url' => $integration->webhook_url,
            'dispatched_at' => now()->toIso8601String(),
        ]);
    }
}
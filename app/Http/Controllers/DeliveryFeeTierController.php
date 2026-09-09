<?php

namespace App\Http\Controllers;

use App\Models\DeliveryFeeTier;
use Illuminate\Http\Request;

class DeliveryFeeTierController extends Controller
{
    /**
     * List all delivery fee tiers.
     */
    public function index()
    {
        $tiers = DeliveryFeeTier::orderBy('min_distance_miles', 'asc')->get();

        return response()->json([
            'status' => 200,
            'data' => $tiers,
            'message' => 'Delivery fee tiers retrieved successfully.',
        ]);
    }

    /**
     * Customer-facing API: Match distance to a single delivery fee tier.
     */
    public function matchDistance(Request $request)
    {
        $distance = $request->input('distance') ?? $request->input('distance_miles');

        if ($distance === null) {
            return response()->json([
                'status' => 422,
                'message' => 'The distance or distance_miles parameter is required.',
            ], 422);
        }

        $distance = (float) $distance;

        // Find active tier where distance falls within range
        $tier = DeliveryFeeTier::where('is_active', true)
            ->where('min_distance_miles', '<=', $distance)
            ->where(function ($query) use ($distance) {
                $query->where('max_distance_miles', '>=', $distance)
                      ->orWhereNull('max_distance_miles');
            })
            ->orderBy('min_distance_miles', 'desc')
            ->first();

        // Fallback to tier with highest min_distance_miles if distance exceeds maximum range
        if (!$tier) {
            $tier = DeliveryFeeTier::where('is_active', true)
                ->orderBy('min_distance_miles', 'desc')
                ->first();
        }

        if (!$tier) {
            return response()->json([
                'status' => 404,
                'message' => 'No active delivery fee tier found.',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Delivery fee tier matched successfully.',
            'data' => [
                'distance_miles' => $distance,
                'fee' => (float) $tier->fee,
                'tier' => $tier,
            ],
        ]);
    }

    /**
     * Store a new delivery fee tier.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'min_distance_miles' => 'required|numeric|min:0',
            'max_distance_miles' => 'nullable|numeric|min:0|gt:min_distance_miles',
            'fee' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $tier = DeliveryFeeTier::create($validated);

        return response()->json([
            'status' => 201,
            'data' => $tier,
            'message' => 'Delivery fee tier created successfully across all branches.',
        ], 201);
    }

    /**
     * Display a specific delivery fee tier.
     */
    public function show(DeliveryFeeTier $deliveryFeeTier)
    {
        return response()->json([
            'status' => 200,
            'data' => $deliveryFeeTier,
        ]);
    }

    /**
     * Update an existing delivery fee tier.
     */
    public function update(Request $request, DeliveryFeeTier $deliveryFeeTier)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'min_distance_miles' => 'nullable|numeric|min:0',
            'max_distance_miles' => 'nullable|numeric|min:0',
            'fee' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $deliveryFeeTier->update(array_filter($validated, fn($val) => !is_null($val)));

        return response()->json([
            'status' => 200,
            'data' => $deliveryFeeTier,
            'message' => 'Delivery fee tier updated successfully across all branches.',
        ]);
    }

    /**
     * Delete a delivery fee tier.
     */
    public function destroy(DeliveryFeeTier $deliveryFeeTier)
    {
        $deliveryFeeTier->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Delivery fee tier deleted successfully.',
        ]);
    }
}

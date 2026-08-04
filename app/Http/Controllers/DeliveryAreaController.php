<?php

namespace App\Http\Controllers;

use App\Models\DeliveryArea;
use Illuminate\Http\Request;

class DeliveryAreaController extends Controller
{
    /**
     * Display a listing of delivery areas.
     */
    public function index(Request $request)
    {
        $query = DeliveryArea::with(['branch', 'restaurant']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        if ($request->boolean('is_active')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = strtoupper(trim($request->search));
            $query->where('postcode', 'like', "%{$search}%");
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created delivery area.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'branch_id' => 'nullable|exists:branches,id',
            'postcode' => 'required|string|max:20',
            'delivery_fee' => 'required|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'estimated_delivery_time' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['postcode'] = strtoupper(trim($validated['postcode']));
        if (!isset($validated['restaurant_id'])) {
            $validated['restaurant_id'] = 1;
        }

        $deliveryArea = DeliveryArea::create($validated);

        return response()->json($deliveryArea->load(['branch', 'restaurant']), 201);
    }

    /**
     * Display the specified delivery area.
     */
    public function show(DeliveryArea $deliveryArea)
    {
        return response()->json($deliveryArea->load(['branch', 'restaurant']));
    }

    /**
     * Update the specified delivery area.
     */
    public function update(Request $request, DeliveryArea $deliveryArea)
    {
        $validated = $request->validate([
            'restaurant_id' => 'sometimes|exists:restaurants,id',
            'branch_id' => 'nullable|exists:branches,id',
            'postcode' => 'sometimes|string|max:20',
            'delivery_fee' => 'sometimes|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'estimated_delivery_time' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['postcode'])) {
            $validated['postcode'] = strtoupper(trim($validated['postcode']));
        }

        $deliveryArea->update($validated);

        return response()->json($deliveryArea->load(['branch', 'restaurant']));
    }

    /**
     * Remove the specified delivery area.
     */
    public function destroy(DeliveryArea $deliveryArea)
    {
        $deliveryArea->delete();

        return response()->json(null, 204);
    }

    /**
     * Check if a customer postcode is serviceable for delivery.
     */
    public function checkPostcode(Request $request)
    {
        $validated = $request->validate([
            'postcode' => 'required|string',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $inputPostcode = strtoupper(trim($validated['postcode']));
        // Match exact or prefix postcode (e.g. "NW1" or "NW1 6XE")
        $query = DeliveryArea::where('is_active', true)
            ->where(function ($q) use ($inputPostcode) {
                $q->where('postcode', $inputPostcode)
                  ->orWhereRaw('? LIKE CONCAT(postcode, "%")', [$inputPostcode]);
            });

        if (!empty($validated['branch_id'])) {
            $query->where('branch_id', $validated['branch_id']);
        }

        $area = $query->first();

        if (!$area) {
            return response()->json([
                'serviceable' => false,
                'message' => 'Sorry, delivery is not available for postcode ' . $inputPostcode,
            ], 404);
        }

        return response()->json([
            'serviceable' => true,
            'message' => 'Delivery is available!',
            'delivery_area' => $area->load(['branch']),
            'delivery_fee' => (float) $area->delivery_fee,
            'minimum_order' => (float) $area->minimum_order,
            'estimated_delivery_time_mins' => $area->estimated_delivery_time ?? 30,
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RestaurantTableController extends Controller
{
    /**
     * Display a listing of restaurant tables.
     */
    public function index(Request $request)
    {
        $query = RestaurantTable::with(['restaurant', 'branch']);

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('min_capacity')) {
            $query->where('capacity', '>=', (int)$request->min_capacity);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'table_number');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'table_number', 'capacity', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('table_number', 'asc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created restaurant table in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'branch_id' => 'nullable|exists:branches,id',
            'table_number' => 'required|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'qr_code' => 'nullable|string|max:255',
            'status' => 'nullable|in:available,reserved,occupied',
        ]);

        if (empty($validated['capacity'])) {
            $validated['capacity'] = 2;
        }

        if (empty($validated['status'])) {
            $validated['status'] = 'available';
        }

        if (empty($validated['qr_code'])) {
            $validated['qr_code'] = 'TBL-QR-' . strtoupper(Str::random(10));
        }

        $restaurantTable = RestaurantTable::create($validated);

        return response()->json($restaurantTable->load(['restaurant', 'branch']), 201);
    }

    /**
     * Display the specified restaurant table.
     */
    public function show(RestaurantTable $restaurantTable)
    {
        return response()->json($restaurantTable->load(['restaurant', 'branch', 'reservations']));
    }

    /**
     * Update the specified restaurant table in storage.
     */
    public function update(Request $request, RestaurantTable $restaurantTable)
    {
        $validated = $request->validate([
            'restaurant_id' => 'sometimes|required|exists:restaurants,id',
            'branch_id' => 'nullable|exists:branches,id',
            'table_number' => 'sometimes|required|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'qr_code' => 'nullable|string|max:255',
            'status' => 'sometimes|in:available,reserved,occupied',
        ]);

        $restaurantTable->update($validated);

        return response()->json($restaurantTable->load(['restaurant', 'branch']));
    }

    /**
     * Remove the specified restaurant table from storage.
     */
    public function destroy(RestaurantTable $restaurantTable)
    {
        $restaurantTable->delete();

        return response()->json(null, 204);
    }

    /**
     * Update table status (available, reserved, occupied).
     */
    public function updateStatus(Request $request, RestaurantTable $restaurantTable)
    {
        $validated = $request->validate([
            'status' => 'required|in:available,reserved,occupied',
        ]);

        $restaurantTable->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => "Table status updated to {$validated['status']}.",
            'data' => $restaurantTable->fresh(['restaurant', 'branch']),
        ]);
    }

    /**
     * Generate or refresh table QR code.
     */
    public function generateQrCode(RestaurantTable $restaurantTable)
    {
        $qrCode = 'TBL-QR-' . strtoupper(Str::random(12));
        $restaurantTable->update(['qr_code' => $qrCode]);

        return response()->json([
            'success' => true,
            'message' => 'QR code generated successfully.',
            'qr_code' => $qrCode,
            'data' => $restaurantTable,
        ]);
    }
}
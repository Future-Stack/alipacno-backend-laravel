<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of branches.
     */
    public function index(Request $request)
    {
        $query = Branch::with(['settings', 'restaurant']);

        if ($request->boolean('is_active')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 20)));
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'opening_time' => 'nullable|string',
            'closing_time' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $branch = Branch::create($validated);

        // Create default settings for branch
        $branch->settings()->create([
            'delivery_radius_km' => 10,
            'min_order_amount' => 15.00,
            'delivery_fee' => 0.00,
            'vat_percentage' => 10,
        ]);

        return response()->json($branch->load('settings'), 201);
    }

    /**
     * Display the specified branch.
     */
    public function show(Branch $branch)
    {
        return response()->json($branch->load(['settings', 'restaurant', 'tables', 'deliveryAreas']));
    }

    /**
     * Update the specified branch in storage.
     */
    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'restaurant_id' => 'sometimes|exists:restaurants,id',
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'opening_time' => 'nullable|string',
            'closing_time' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $branch->update($validated);

        return response()->json($branch->load('settings'));
    }

    /**
     * Remove the specified branch from storage.
     */
    public function destroy(Branch $branch)
    {
        $branch->delete();

        return response()->json(null, 204);
    }
}
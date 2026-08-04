<?php

namespace App\Http\Controllers;

use App\Models\SpiceLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpiceLevelController extends Controller
{
    /**
     * Display a listing of spice levels.
     */
    public function index(Request $request)
    {
        $query = SpiceLevel::with(['restaurant', 'menuItem']);

        if ($request->filled('restaurant_id')) {
            $query->forRestaurant($request->restaurant_id);
        }

        if ($request->filled('menu_item_id')) {
            $query->forMenuItem($request->menu_item_id);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSorts = ['id', 'name', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('name', 'asc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created spice level in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'name' => 'required|string|max:255',
        ]);

        $spiceLevel = SpiceLevel::create($validated);

        return response()->json($spiceLevel->load(['restaurant', 'menuItem']), 201);
    }

    /**
     * Display the specified spice level.
     */
    public function show(SpiceLevel $spiceLevel)
    {
        return response()->json($spiceLevel->load(['restaurant', 'menuItem']));
    }

    /**
     * Update the specified spice level in storage.
     */
    public function update(Request $request, SpiceLevel $spiceLevel)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'name' => 'sometimes|required|string|max:255',
        ]);

        $spiceLevel->update($validated);

        return response()->json($spiceLevel->load(['restaurant', 'menuItem']));
    }

    /**
     * Remove the specified spice level from storage.
     */
    public function destroy(SpiceLevel $spiceLevel)
    {
        $spiceLevel->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk create multiple spice levels.
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'levels' => 'required|array|min:1',
            'levels.*' => 'required|string|max:255',
        ]);

        $created = DB::transaction(function () use ($validated) {
            $records = [];
            foreach ($validated['levels'] as $name) {
                $records[] = SpiceLevel::create([
                    'restaurant_id' => $validated['restaurant_id'] ?? null,
                    'menu_item_id' => $validated['menu_item_id'] ?? null,
                    'name' => $name,
                ]);
            }
            return $records;
        });

        return response()->json([
            'success' => true,
            'message' => count($created) . ' spice levels created successfully.',
            'data' => $created,
        ], 201);
    }
}
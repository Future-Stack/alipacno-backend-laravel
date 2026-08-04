<?php

namespace App\Http\Controllers;

use App\Models\CookingPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CookingPreferenceController extends Controller
{
    /**
     * Display a listing of cooking preferences.
     */
    public function index(Request $request)
    {
        $query = CookingPreference::with(['restaurant', 'menuItem']);

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
     * Store a newly created cooking preference in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'name' => 'required|string|max:255',
        ]);

        $preference = CookingPreference::create($validated);

        return response()->json($preference->load(['restaurant', 'menuItem']), 201);
    }

    /**
     * Display the specified cooking preference.
     */
    public function show(CookingPreference $cookingPreference)
    {
        return response()->json($cookingPreference->load(['restaurant', 'menuItem']));
    }

    /**
     * Update the specified cooking preference in storage.
     */
    public function update(Request $request, CookingPreference $cookingPreference)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'name' => 'sometimes|required|string|max:255',
        ]);

        $cookingPreference->update($validated);

        return response()->json($cookingPreference->load(['restaurant', 'menuItem']));
    }

    /**
     * Remove the specified cooking preference from storage.
     */
    public function destroy(CookingPreference $cookingPreference)
    {
        $cookingPreference->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk store multiple cooking preferences.
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'preferences' => 'required|array|min:1',
            'preferences.*.name' => 'required|string|max:255',
        ]);

        $restaurantId = $validated['restaurant_id'] ?? null;
        $menuItemId = $validated['menu_item_id'] ?? null;

        $created = DB::transaction(function () use ($validated, $restaurantId, $menuItemId) {
            $records = [];
            foreach ($validated['preferences'] as $prefData) {
                $records[] = CookingPreference::create([
                    'restaurant_id' => $prefData['restaurant_id'] ?? $restaurantId,
                    'menu_item_id' => $prefData['menu_item_id'] ?? $menuItemId,
                    'name' => $prefData['name'],
                ]);
            }
            return $records;
        });

        return response()->json([
            'success' => true,
            'message' => count($created) . ' cooking preferences created successfully.',
            'data' => $created,
        ], 201);
    }
}
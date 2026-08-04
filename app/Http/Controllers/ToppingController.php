<?php

namespace App\Http\Controllers;

use App\Models\Topping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ToppingController extends Controller
{
    /**
     * Display a listing of toppings.
     */
    public function index(Request $request)
    {
        $query = Topping::with(['restaurant', 'menuItem']);

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
        $allowedSorts = ['id', 'name', 'price', 'created_at'];

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
     * Store a newly created topping in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        $topping = Topping::create($validated);

        return response()->json($topping->load(['restaurant', 'menuItem']), 201);
    }

    /**
     * Display the specified topping.
     */
    public function show(Topping $topping)
    {
        return response()->json($topping->load(['restaurant', 'menuItem']));
    }

    /**
     * Update the specified topping in storage.
     */
    public function update(Request $request, Topping $topping)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
        ]);

        $topping->update($validated);

        return response()->json($topping->load(['restaurant', 'menuItem']));
    }

    /**
     * Remove the specified topping from storage.
     */
    public function destroy(Topping $topping)
    {
        $topping->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk store multiple toppings.
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'toppings' => 'required|array|min:1',
            'toppings.*.name' => 'required|string|max:255',
            'toppings.*.price' => 'required|numeric|min:0',
        ]);

        $restaurantId = $validated['restaurant_id'] ?? null;
        $menuItemId = $validated['menu_item_id'] ?? null;

        $created = DB::transaction(function () use ($validated, $restaurantId, $menuItemId) {
            $records = [];
            foreach ($validated['toppings'] as $toppingData) {
                $records[] = Topping::create([
                    'restaurant_id' => $toppingData['restaurant_id'] ?? $restaurantId,
                    'menu_item_id' => $toppingData['menu_item_id'] ?? $menuItemId,
                    'name' => $toppingData['name'],
                    'price' => $toppingData['price'],
                ]);
            }
            return $records;
        });

        return response()->json([
            'success' => true,
            'message' => count($created) . ' toppings created successfully.',
            'data' => $created,
        ], 201);
    }
}
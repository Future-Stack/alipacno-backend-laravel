<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    /**
     * Display a listing of restaurants.
     */
    public function index(Request $request)
    {
        $query = Restaurant::with(['branches.settings', 'categories']);

        if ($request->boolean('is_active')) {
            $query->where('status', 'active');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created restaurant.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:restaurants,slug',
            'logo' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'postcode' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,closed',
        ]);

        $restaurant = Restaurant::create($validated);

        return response()->json($restaurant, 201);
    }

    /**
     * Display the specified restaurant.
     */
    public function show(Restaurant $restaurant)
    {
        return response()->json($restaurant->load(['branches.settings', 'categories']));
    }

    /**
     * Update the specified restaurant in storage.
     */
    public function update(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:restaurants,slug,' . $restaurant->id,
            'logo' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'postcode' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,closed',
        ]);

        $restaurant->update($validated);

        return response()->json($restaurant);
    }

    /**
     * Remove the specified restaurant from storage.
     */
    public function destroy(Restaurant $restaurant)
    {
        $restaurant->delete();

        return response()->json(null, 204);
    }
}
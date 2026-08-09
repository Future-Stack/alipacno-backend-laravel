<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class MenuItemController extends Controller
{
    /**
     * Display a listing of menu items with filtering and relations.
     */
    public function index(Request $request)
    {
        $query = MenuItem::with(['category', 'sizes', 'cookingPreferences', 'spiceLevels', 'toppings']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('category_name')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->category_name . '%');
            });
        }

        if ($request->filled('branch_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id)->orWhereNull('branch_id');
            });
        }

        if ($request->filled('is_popular')) {
            $query->where('is_popular', filter_var($request->is_popular, FILTER_VALIDATE_BOOLEAN));
        }


        if ($request->filled('is_happy_hour_eligible')) {
            $query->where('is_happy_hour_eligible', filter_var($request->is_happy_hour_eligible, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'available');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        $perPage = $request->input('per_page', 15);
        return response()->json($query->paginate($perPage));
    }

    /**
     * Store a newly created menu item in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'branch_id' => 'nullable|exists:branches,id',
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'preparation_time' => 'nullable|integer',
            'calories' => 'nullable|integer',
            'rating' => 'nullable|numeric|between:0,5',
            'review_count' => 'nullable|integer',
            'is_popular' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'is_happy_hour_eligible' => 'nullable|boolean',
            'status' => 'nullable|in:available,unavailable,out_of_stock',
            'image' => 'nullable',
            'sizes' => 'nullable|array',
            'cooking_preferences' => 'nullable|array',
            'spice_levels' => 'nullable|array',
            'toppings' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,webp,gif|max:5120']);
            $validated['image'] = $request->file('image')->store('menu_items', 'public');
        }

        $menuItem = MenuItem::create($validated);

        // Attach Sizes if provided
        if (!empty($validated['sizes'])) {
            foreach ($validated['sizes'] as $size) {
                $menuItem->sizes()->create([
                    'name' => $size['name'],
                    'size_description' => $size['size_description'] ?? null,
                    'extra_price' => $size['extra_price'] ?? 0,
                ]);
            }
        }

        // Attach Cooking Preferences
        if (!empty($validated['cooking_preferences'])) {
            foreach ($validated['cooking_preferences'] as $pref) {
                $menuItem->cookingPreferences()->create([
                    'name' => is_array($pref) ? $pref['name'] : $pref,
                ]);
            }
        }

        // Attach Spice Levels
        if (!empty($validated['spice_levels'])) {
            foreach ($validated['spice_levels'] as $spice) {
                $menuItem->spiceLevels()->create([
                    'name' => is_array($spice) ? $spice['name'] : $spice,
                ]);
            }
        }

        // Attach Toppings
        if (!empty($validated['toppings'])) {
            foreach ($validated['toppings'] as $topping) {
                $menuItem->toppings()->create([
                    'name' => $topping['name'],
                    'price' => $topping['price'] ?? 0,
                ]);
            }
        }

        return response()->json($menuItem->load(['category', 'sizes', 'cookingPreferences', 'spiceLevels', 'toppings']), 201);
    }

    /**
     * Display the specified menu item.
     */
    public function show(MenuItem $menuItem)
    {
        return response()->json($menuItem->load(['category', 'sizes', 'cookingPreferences', 'spiceLevels', 'toppings', 'reviews']));
    }

    /**
     * Update the specified menu item in storage.
     */
    public function update(Request $request, MenuItem $menuItem)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'branch_id' => 'nullable|exists:branches,id',
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'preparation_time' => 'nullable|integer',
            'calories' => 'nullable|integer',
            'rating' => 'nullable|numeric|between:0,5',
            'review_count' => 'nullable|integer',
            'is_popular' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'is_happy_hour_eligible' => 'nullable|boolean',
            'status' => 'sometimes|in:available,unavailable,out_of_stock',
            'image' => 'nullable',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);
        }

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,webp,gif|max:5120']);
            if ($menuItem->image && Storage::disk('public')->exists($menuItem->image)) {
                Storage::disk('public')->delete($menuItem->image);
            }
            $validated['image'] = $request->file('image')->store('menu_items', 'public');
        }

        $menuItem->update($validated);

        return response()->json($menuItem->load(['category', 'sizes', 'cookingPreferences', 'spiceLevels', 'toppings']));
    }

    /**
     * Remove the specified menu item from storage.
     */
    public function destroy(MenuItem $menuItem)
    {
        if ($menuItem->image && Storage::disk('public')->exists($menuItem->image)) {
            Storage::disk('public')->delete($menuItem->image);
        }

        $menuItem->delete();

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers\Subcategory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubcategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Subcategory::withCount('menuItems');

        if ($request->boolean('is_active')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');

        if ($request->boolean('all')) {
            return response()->json([
                'success' => true,
                'data' => $query->with('category')->get()]);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 50))]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string',
            'image' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $subcategories = Subcategory::create($validated);

        return response()->json([
            'success' => true,
            'data' => $subcategories
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Subcategory $subcategory)
    {
        return response()->json($subcategory->load('menuItems.sizes'));
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Subcategory $subcategory)
    {
        $validated = $request->validate([
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'category_id' => 'required|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'icon' => 'nullable|string',
            'image' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $subcategory->update($validated);

        return response()->json($subcategory);
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Subcategory $subcategory)
    {

        try {
            $subcategory->delete();

            return response()->json([
                'success' => true,
                'message' => 'Subcategory has been deleted'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

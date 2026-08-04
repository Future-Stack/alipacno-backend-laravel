<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use Illuminate\Http\Request;

class InventoryCategoryController extends Controller
{
    /**
     * Display a listing of inventory categories.
     */
    public function index(Request $request)
    {
        $query = InventoryCategory::with('branch')->withCount('items');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created inventory category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $category = InventoryCategory::create($validated);

        return response()->json($category->load('branch'), 201);
    }

    /**
     * Display the specified inventory category.
     */
    public function show(InventoryCategory $inventoryCategory)
    {
        return response()->json($inventoryCategory->load(['branch', 'items']));
    }

    /**
     * Update the specified inventory category.
     */
    public function update(Request $request, InventoryCategory $inventoryCategory)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|exists:branches,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $inventoryCategory->update($validated);

        return response()->json($inventoryCategory->load('branch'));
    }

    /**
     * Remove the specified inventory category.
     */
    public function destroy(InventoryCategory $inventoryCategory)
    {
        if ($inventoryCategory->items()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete inventory category with existing inventory items. Please reassign or remove the items first.'
            ], 422);
        }

        $inventoryCategory->delete();

        return response()->json(null, 204);
    }
}
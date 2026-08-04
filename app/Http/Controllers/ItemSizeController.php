<?php

namespace App\Http\Controllers;

use App\Models\ItemSize;
use Illuminate\Http\Request;

class ItemSizeController extends Controller
{
    /**
     * Display a listing of menu item sizes.
     */
    public function index(Request $request)
    {
        $query = ItemSize::with('menuItem');

        if ($request->filled('menu_item_id')) {
            $query->where('menu_item_id', $request->menu_item_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('size_description', 'like', "%{$search}%");
            });
        }

        $query->orderBy('extra_price', 'asc');

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created menu item size.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
            'name' => 'required|string|max:255',
            'size_description' => 'nullable|string|max:255',
            'extra_price' => 'nullable|numeric|min:0',
        ]);

        if (!isset($validated['extra_price'])) {
            $validated['extra_price'] = 0.00;
        }

        $size = ItemSize::create($validated);

        return response()->json($size->load('menuItem'), 201);
    }

    /**
     * Display the specified menu item size.
     */
    public function show(ItemSize $itemSize)
    {
        return response()->json($itemSize->load('menuItem'));
    }

    /**
     * Update the specified menu item size.
     */
    public function update(Request $request, ItemSize $itemSize)
    {
        $validated = $request->validate([
            'menu_item_id' => 'sometimes|exists:menu_items,id',
            'name' => 'sometimes|string|max:255',
            'size_description' => 'nullable|string|max:255',
            'extra_price' => 'nullable|numeric|min:0',
        ]);

        $itemSize->update($validated);

        return response()->json($itemSize->load('menuItem'));
    }

    /**
     * Remove the specified menu item size.
     */
    public function destroy(ItemSize $itemSize)
    {
        $itemSize->delete();

        return response()->json(null, 204);
    }
}
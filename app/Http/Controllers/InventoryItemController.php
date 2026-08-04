<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    /**
     * Display a listing of inventory items.
     */
    public function index(Request $request)
    {
        $query = InventoryItem::with(['branch', 'category']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('low_stock_only')) {
            $query->whereRaw('quantity <= minimum_stock');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created inventory item.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'category_id' => 'required|exists:inventory_categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:inventory_items,sku',
            'quantity' => 'required|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
            'unit' => 'required|string|max:50',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:in_stock,low_stock,out_of_stock',
        ]);

        $minStock = $validated['minimum_stock'] ?? 0;
        $qty = $validated['quantity'];

        if (!isset($validated['status'])) {
            if ($qty <= 0) {
                $validated['status'] = 'out_of_stock';
            } elseif ($qty <= $minStock) {
                $validated['status'] = 'low_stock';
            } else {
                $validated['status'] = 'in_stock';
            }
        }

        $item = InventoryItem::create($validated);

        return response()->json($item->load(['branch', 'category']), 201);
    }

    /**
     * Display the specified inventory item.
     */
    public function show(InventoryItem $inventoryItem)
    {
        return response()->json($inventoryItem->load(['branch', 'category', 'transactions']));
    }

    /**
     * Update the specified inventory item.
     */
    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|exists:branches,id',
            'category_id' => 'sometimes|exists:inventory_categories,id',
            'name' => 'sometimes|string|max:255',
            'sku' => 'nullable|string|max:100|unique:inventory_items,sku,' . $inventoryItem->id,
            'quantity' => 'sometimes|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
            'unit' => 'sometimes|string|max:50',
            'purchase_price' => 'sometimes|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:in_stock,low_stock,out_of_stock',
        ]);

        $qty = $validated['quantity'] ?? $inventoryItem->quantity;
        $minStock = $validated['minimum_stock'] ?? $inventoryItem->minimum_stock;

        if (!isset($validated['status'])) {
            if ($qty <= 0) {
                $validated['status'] = 'out_of_stock';
            } elseif ($qty <= $minStock) {
                $validated['status'] = 'low_stock';
            } else {
                $validated['status'] = 'in_stock';
            }
        }

        $inventoryItem->update($validated);

        return response()->json($inventoryItem->load(['branch', 'category']));
    }

    /**
     * Remove the specified inventory item.
     */
    public function destroy(InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();

        return response()->json(null, 204);
    }
}
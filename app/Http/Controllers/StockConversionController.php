<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockConversion;
use Illuminate\Http\Request;

class StockConversionController extends Controller
{
    /**
     * Display a listing of stock conversions.
     */
    public function index(Request $request)
    {
        $query = StockConversion::with(['branch', 'inventoryItem', 'convertedItem', 'creator']);

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('inventory_item_id')) {
            $query->where('inventory_item_id', $request->inventory_item_id);
        }

        if ($request->filled('converted_item_id')) {
            $query->where('converted_item_id', $request->converted_item_id);
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'quantity', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created stock conversion in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'converted_item_id' => 'required|exists:inventory_items,id|different:inventory_item_id',
            'quantity' => 'required|numeric|gt:0',
        ]);

        $validated['created_by'] = auth()->id();

        $conversion = StockConversion::create($validated);

        return response()->json($conversion->load(['branch', 'inventoryItem', 'convertedItem', 'creator']), 201);
    }

    /**
     * Display the specified stock conversion.
     */
    public function show(StockConversion $stockConversion)
    {
        return response()->json($stockConversion->load(['branch', 'inventoryItem', 'convertedItem', 'creator']));
    }

    /**
     * Update the specified stock conversion in storage.
     */
    public function update(Request $request, StockConversion $stockConversion)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|required|exists:branches,id',
            'inventory_item_id' => 'sometimes|required|exists:inventory_items,id',
            'converted_item_id' => 'sometimes|required|exists:inventory_items,id|different:inventory_item_id',
            'quantity' => 'sometimes|required|numeric|gt:0',
        ]);

        $stockConversion->update($validated);

        return response()->json($stockConversion->load(['branch', 'inventoryItem', 'convertedItem', 'creator']));
    }

    /**
     * Remove the specified stock conversion from storage.
     */
    public function destroy(StockConversion $stockConversion)
    {
        $stockConversion->delete();

        return response()->json(null, 204);
    }

    /**
     * Calculate conversion estimate between inventory items.
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'converted_item_id' => 'required|exists:inventory_items,id|different:inventory_item_id',
            'quantity' => 'required|numeric|gt:0',
        ]);

        $sourceItem = InventoryItem::findOrFail($validated['inventory_item_id']);
        $targetItem = InventoryItem::findOrFail($validated['converted_item_id']);

        $quantity = (float) $validated['quantity'];

        return response()->json([
            'success' => true,
            'source_item' => [
                'id' => $sourceItem->id,
                'name' => $sourceItem->name,
                'unit' => $sourceItem->unit ?? 'unit',
                'quantity' => $quantity,
            ],
            'target_item' => [
                'id' => $targetItem->id,
                'name' => $targetItem->name,
                'unit' => $targetItem->unit ?? 'unit',
                'estimated_quantity' => $quantity,
            ],
            'conversion_factor' => 1.0,
        ]);
    }
}
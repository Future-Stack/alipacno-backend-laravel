<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockConversion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * Store a newly created stock conversion in storage, moving stock from the
     * source item to the converted item.
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

        $sourceItem = InventoryItem::findOrFail($validated['inventory_item_id']);

        if ($validated['quantity'] > $sourceItem->quantity) {
            return response()->json(['message' => 'Insufficient stock in source item for this conversion.'], 422);
        }

        $conversion = DB::transaction(function () use ($validated) {
            $conversion = StockConversion::create($validated);

            $this->applyStockDelta($validated['inventory_item_id'], -$validated['quantity']);
            $this->applyStockDelta($validated['converted_item_id'], $validated['quantity']);

            return $conversion;
        });

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
     * Update the specified stock conversion in storage, reconciling the stock
     * impact of the previous values before applying the new ones.
     */
    public function update(Request $request, StockConversion $stockConversion)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|required|exists:branches,id',
            'inventory_item_id' => 'sometimes|required|exists:inventory_items,id',
            'converted_item_id' => 'sometimes|required|exists:inventory_items,id|different:inventory_item_id',
            'quantity' => 'sometimes|required|numeric|gt:0',
        ]);

        $newSourceId = $validated['inventory_item_id'] ?? $stockConversion->inventory_item_id;
        $newTargetId = $validated['converted_item_id'] ?? $stockConversion->converted_item_id;
        $newQuantity = $validated['quantity'] ?? $stockConversion->quantity;

        DB::transaction(function () use ($stockConversion, $validated, $newSourceId, $newTargetId, $newQuantity) {
            // Reverse the previous conversion's effect on stock.
            $this->applyStockDelta($stockConversion->inventory_item_id, $stockConversion->quantity);
            $this->applyStockDelta($stockConversion->converted_item_id, -$stockConversion->quantity);

            // Apply the new conversion's effect on stock.
            $this->applyStockDelta($newSourceId, -$newQuantity);
            $this->applyStockDelta($newTargetId, $newQuantity);

            $stockConversion->update($validated);
        });

        return response()->json($stockConversion->load(['branch', 'inventoryItem', 'convertedItem', 'creator']));
    }

    /**
     * Remove the specified stock conversion from storage, reversing its stock impact.
     */
    public function destroy(StockConversion $stockConversion)
    {
        DB::transaction(function () use ($stockConversion) {
            $this->applyStockDelta($stockConversion->inventory_item_id, $stockConversion->quantity);
            $this->applyStockDelta($stockConversion->converted_item_id, -$stockConversion->quantity);

            $stockConversion->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Stock conversion deleted successfully.'
        ], 200);
    }

    /**
     * Calculate conversion estimate between inventory items, using the target
     * item's stored conversion ratio (pack_size -> yield_qty) when available.
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

        $hasRatio = $targetItem->made_from_item_id === $sourceItem->id
            && $targetItem->pack_size > 0
            && $targetItem->yield_qty !== null;

        $conversionFactor = $hasRatio ? ((float) $targetItem->yield_qty / (float) $targetItem->pack_size) : 1.0;
        $estimatedQuantity = $hasRatio ? round($quantity * $conversionFactor, 2) : $quantity;

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
                'estimated_quantity' => $estimatedQuantity,
            ],
            'conversion_factor' => $conversionFactor,
        ]);
    }

    /**
     * Convert whole "packs" of a raw material into its prepared item, using the
     * prepared item's own stored conversion ratio (pack_size -> yield_qty).
     */
    public function convert(Request $request)
    {
        $validated = $request->validate([
            'prepared_item_id' => 'required|exists:inventory_items,id',
            'packs' => 'required|numeric|gt:0',
        ]);

        $preparedItem = InventoryItem::findOrFail($validated['prepared_item_id']);

        if (!$preparedItem->made_from_item_id || !$preparedItem->pack_size || $preparedItem->yield_qty === null) {
            return response()->json(['message' => 'This item has no conversion ratio configured (made from / pack size / yield).'], 422);
        }

        $rawItem = InventoryItem::findOrFail($preparedItem->made_from_item_id);
        $yield = $preparedItem->conversionYieldFor((float) $validated['packs']);

        if ($yield['raw_consumed'] > $rawItem->quantity) {
            return response()->json(['message' => 'Insufficient raw material stock for this conversion.'], 422);
        }

        $conversion = DB::transaction(function () use ($rawItem, $preparedItem, $yield) {
            $conversion = StockConversion::create([
                'branch_id' => $rawItem->branch_id,
                'inventory_item_id' => $rawItem->id,
                'converted_item_id' => $preparedItem->id,
                'quantity' => $yield['raw_consumed'],
                'created_by' => auth()->id(),
            ]);

            $this->applyStockDelta($rawItem->id, -$yield['raw_consumed']);
            $this->applyStockDelta($preparedItem->id, $yield['yield_produced']);

            return $conversion;
        });

        return response()->json([
            'conversion' => $conversion->load(['branch', 'inventoryItem', 'convertedItem']),
            'raw_consumed' => $yield['raw_consumed'],
            'raw_unit' => $yield['raw_unit'],
            'yield_produced' => $yield['yield_produced'],
            'yield_unit' => $yield['yield_unit'],
        ]);
    }

    private function applyStockDelta(int $inventoryItemId, float $delta): void
    {
        $item = InventoryItem::findOrFail($inventoryItemId);
        $item->quantity = max(0, $item->quantity + $delta);

        if ($item->quantity <= 0) {
            $item->status = 'out_of_stock';
        } elseif ($item->quantity <= $item->minimum_stock) {
            $item->status = 'low_stock';
        } else {
            $item->status = 'in_stock';
        }

        $item->save();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesInventoryStock;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\StockConversion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockConversionController extends Controller
{
    use ManagesInventoryStock;

    /**
     * Display a listing of stock conversions. (unchanged)
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
     * Store a newly created stock conversion, moving stock from the source
     * item to the converted item.
     *
     * FIX: the "enough stock?" check used to run BEFORE the DB transaction
     * against a possibly-stale $sourceItem, then applyStockDelta() used to
     * silently clamp to 0 instead of failing — so two concurrent requests
     * could both pass the check and the second would silently under-deduct.
     * Now the row is locked and the check happens via the trait's
     * applyStockDelta(), which throws (and rolls back) on insufficient stock.
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

        try {
            $conversion = DB::transaction(function () use ($validated) {
                $conversion = StockConversion::create($validated);

                $this->applyStockDelta($validated['inventory_item_id'], -$validated['quantity']);
                $this->applyStockDelta($validated['converted_item_id'], $validated['quantity']);

                return $conversion;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($conversion->load(['branch', 'inventoryItem', 'convertedItem', 'creator']), 201);
    }

    /**
     * Display the specified stock conversion. (unchanged)
     */
    public function show(StockConversion $stockConversion)
    {
        return response()->json($stockConversion->load(['branch', 'inventoryItem', 'convertedItem', 'creator']));
    }

    /**
     * Update the specified stock conversion, reconciling the stock impact of
     * the previous values before applying the new ones.
     *
     * FIX: previously had NO sufficiency check on the new quantity/items —
     * it reversed the old effect then applied the new delta straight through
     * applyStockDelta()'s old max(0, ...) clamp, so an over-large edit would
     * silently zero out a source item instead of being rejected. Now every
     * delta (reversal AND re-application) goes through the trait, which
     * throws on insufficient stock and rolls the whole edit back.
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

        try {
            DB::transaction(function () use ($stockConversion, $validated, $newSourceId, $newTargetId, $newQuantity) {
                if ((int) $newSourceId === (int) $stockConversion->inventory_item_id && (int) $newTargetId === (int) $stockConversion->converted_item_id) {
                    $qtyDiff = round((float) $newQuantity - (float) $stockConversion->quantity, 4);
                    if ($qtyDiff !== 0.0) {
                        $this->applyStockDelta($newSourceId, -$qtyDiff);
                        $this->applyStockDelta($newTargetId, $qtyDiff);
                    }
                } else {
                    // Reverse the previous conversion's effect on stock.
                    $this->applyStockDelta($stockConversion->inventory_item_id, $stockConversion->quantity);
                    $this->applyStockDelta($stockConversion->converted_item_id, -$stockConversion->quantity);

                    // Apply the new conversion's effect on stock.
                    $this->applyStockDelta($newSourceId, -$newQuantity);
                    $this->applyStockDelta($newTargetId, $newQuantity);
                }

                $stockConversion->update($validated);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($stockConversion->fresh()->load(['branch', 'inventoryItem', 'convertedItem', 'creator']));
    }

    /**
     * Remove the specified stock conversion, reversing its stock impact.
     * (Same trait-based delta, now also throws/422s instead of clamping.)
     */
    public function destroy(StockConversion $stockConversion)
    {
        try {
            DB::transaction(function () use ($stockConversion) {
                // Restore source item stock
                $this->applyStockDelta($stockConversion->inventory_item_id, $stockConversion->quantity);

                // Deduct target item stock up to what is available on-hand
                $targetItem = InventoryItem::where('id', $stockConversion->converted_item_id)->lockForUpdate()->first();
                if ($targetItem) {
                    $deductQty = min((float) $targetItem->quantity, (float) $stockConversion->quantity);
                    if ($deductQty > 0) {
                        $this->applyStockDelta($stockConversion->converted_item_id, -$deductQty);
                    }
                }

                $stockConversion->delete();
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->noContent();
    }

    /**
     * Calculate conversion estimate between inventory items. (unchanged —
     * read-only, no stock is touched.)
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
     * Convert whole "packs" of a raw material into its prepared item.
     *
     * FIXES:
     *  - now locks the raw item row and wraps the check + StockConversion
     *    create + both stock deltas in one DB transaction (previously the
     *    sufficiency check ran outside any lock/transaction — same race
     *    condition as store() had).
     *  - now ALSO writes two InventoryTransaction rows ('conversion_out' on
     *    the raw item, 'conversion_in' on the prepared item). Previously
     *    convert() only wrote a StockConversion row, so summary()/analytics()
     *    — which read from inventory_transactions — never saw conversions at
     *    all, undercounting consumption/turnover.
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

        $yield = $preparedItem->conversionYieldFor((float) $validated['packs']);

        try {
            [$conversion, $rawItem] = DB::transaction(function () use ($preparedItem, $yield) {
                $rawItem = InventoryItem::where('id', $preparedItem->made_from_item_id)->lockForUpdate()->firstOrFail();

                $conversion = StockConversion::create([
                    'branch_id' => $rawItem->branch_id,
                    'inventory_item_id' => $rawItem->id,
                    'converted_item_id' => $preparedItem->id,
                    'quantity' => $yield['raw_consumed'],
                    'created_by' => auth()->id(),
                ]);

                $this->applyStockDelta($rawItem->id, -$yield['raw_consumed']);
                $this->applyStockDelta($preparedItem->id, $yield['yield_produced']);

                $userId = auth()->id();
                InventoryTransaction::create([
                    'inventory_item_id' => $rawItem->id,
                    'transaction_type' => 'conversion_out',
                    'quantity' => $yield['raw_consumed'],
                    'notes' => "Converted into {$preparedItem->name}",
                    'created_by' => $userId,
                ]);
                InventoryTransaction::create([
                    'inventory_item_id' => $preparedItem->id,
                    'transaction_type' => 'conversion_in',
                    'quantity' => $yield['yield_produced'],
                    'notes' => "Produced from {$rawItem->name}",
                    'created_by' => $userId,
                ]);

                return [$conversion, $rawItem];
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'conversion' => $conversion->load(['branch', 'inventoryItem', 'convertedItem']),
            'raw_consumed' => $yield['raw_consumed'],
            'raw_unit' => $yield['raw_unit'],
            'yield_produced' => $yield['yield_produced'],
            'yield_unit' => $yield['yield_unit'],
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesInventoryStock;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransactionController extends Controller
{
    use ManagesInventoryStock;

    /**
     * Display a listing of inventory transactions. (unchanged)
     */
    public function index(Request $request)
    {
        $query = InventoryTransaction::with(['inventoryItem.branch', 'creator', 'supplier']);

        if ($request->filled('inventory_item_id')) {
            $query->where('inventory_item_id', $request->inventory_item_id);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        // FIX (supplier traceability): lets you ask "everything we've ever
        // bought from supplier X" directly, e.g.
        // GET /inventory-transactions?supplier_id=3
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('inventoryItem', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created inventory transaction & update stock.
     *
     * FIXES:
     *  - transaction_type list extended with 'transfer_in' / 'transfer_out' /
     *    'conversion_in' / 'conversion_out' so the direction-aware types used
     *    by distribute() and convert() are valid if ever submitted here too
     *    (if your DB column is a plain VARCHAR — typical Laravel default —
     *    this needs no migration; if it's a real DB-level ENUM you'll need a
     *    small migration to extend the allowed values).
     *  - added optional `supplier_id` so purchase/restock transactions can be
     *    linked to who supplied the stock (see accompanying migration).
     *  - the row create + stock update now happen in ONE DB transaction with
     *    a locked row, instead of two separate unguarded writes — previously
     *    a failure between them could leave the transaction logged but the
     *    stock unchanged, and concurrent requests could race past the
     *    insufficient-stock check.
     *  - status derivation now goes through the shared trait instead of a
     *    second, separately-maintained copy of the same if/else logic.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'transaction_type' => 'required|in:purchase,sale,waste,adjustment,restock,transfer_in,transfer_out,conversion_in,conversion_out',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            // FIX (supplier traceability): required for purchase/restock so
            // every stock-IN row is always attributable to a supplier —
            // optional (nullable) for every other type, since sale/waste/
            // transfer/conversion/adjustment have nothing to do with a supplier.
            'supplier_id' => 'required_if:transaction_type,purchase,restock|nullable|exists:suppliers,id',
        ]);

        if ($request->user()) {
            $validated['created_by'] = $request->user()->id;
        }

        $type = $validated['transaction_type'];
        $qty = (float) $validated['quantity'];
        $increasesStock = in_array($type, ['purchase', 'restock', 'adjustment', 'transfer_in', 'conversion_in']);

        try {
            $transaction = DB::transaction(function () use ($validated, $qty, $increasesStock) {
                $transaction = InventoryTransaction::create($validated);

                $this->applyStockDelta($validated['inventory_item_id'], $increasesStock ? $qty : -$qty);

                return $transaction;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($transaction->load(['inventoryItem', 'creator', 'supplier']), 201);
    }

    /**
     * Display the specified inventory transaction.
     * FIX: now also loads `supplier` so the response shows who it was bought from.
     */
    public function show(InventoryTransaction $inventoryTransaction)
    {
        return response()->json($inventoryTransaction->load(['inventoryItem.branch', 'creator', 'supplier']));
    }

    /**
     * Update the specified inventory transaction notes. (unchanged — still
     * intentionally notes-only; quantity/type stay immutable after creation
     * so the audit trail can't be silently rewritten.)
     */
    public function update(Request $request, InventoryTransaction $inventoryTransaction)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $inventoryTransaction->update($validated);

        return response()->json($inventoryTransaction->load(['inventoryItem', 'creator']));
    }

    /**
     * Remove the specified inventory transaction.
     *
     * FIX: this previously deleted the row WITHOUT reversing its effect on
     * the item's stock — deleting a 'sale' transaction left the quantity
     * decreased forever, permanently desyncing stock from history. Now the
     * delta is reversed first, inside a locked DB transaction. If reversing
     * would push the item negative (e.g. deleting an old 'purchase' whose
     * stock has since been sold further), the delete is rejected with a
     * clear 422 instead of corrupting the quantity.
     */
    public function destroy(InventoryTransaction $inventoryTransaction)
    {
        try {
            DB::transaction(function () use ($inventoryTransaction) {
                $increasesStock = in_array($inventoryTransaction->transaction_type, ['purchase', 'restock', 'adjustment', 'transfer_in', 'conversion_in']);
                $reverseDelta = $increasesStock ? -$inventoryTransaction->quantity : $inventoryTransaction->quantity;

                $this->applyStockDelta($inventoryTransaction->inventory_item_id, $reverseDelta);

                $inventoryTransaction->delete();
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'Cannot delete this transaction: ' . $e->getMessage() . '. Stock has moved since this transaction was recorded.',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Inventory transaction deleted successfully.'
        ], 200);
    }
}
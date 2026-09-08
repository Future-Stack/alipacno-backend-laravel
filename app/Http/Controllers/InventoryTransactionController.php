<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;

class InventoryTransactionController extends Controller
{
    /**
     * Display a listing of inventory transactions.
     */
    public function index(Request $request)
    {
        $query = InventoryTransaction::with(['inventoryItem.branch', 'creator']);

        if ($request->filled('inventory_item_id')) {
            $query->where('inventory_item_id', $request->inventory_item_id);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
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
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'transaction_type' => 'required|in:purchase,sale,waste,adjustment,restock',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        if ($request->user()) {
            $validated['created_by'] = $request->user()->id;
        }

        $transaction = InventoryTransaction::create($validated);

        // Adjust Inventory Item Stock Level
        $item = InventoryItem::findOrFail($validated['inventory_item_id']);
        $type = $validated['transaction_type'];
        $qty = (float) $validated['quantity'];

        if (in_array($type, ['purchase', 'restock', 'adjustment'])) {
            $item->quantity += $qty;
        } elseif (in_array($type, ['sale', 'waste'])) {
            $item->quantity = max(0, $item->quantity - $qty);
        }

        // Recalculate status
        if ($item->quantity <= 0) {
            $item->status = 'out_of_stock';
        } elseif ($item->quantity <= $item->minimum_stock) {
            $item->status = 'low_stock';
        } else {
            $item->status = 'in_stock';
        }

        $item->save();

        return response()->json($transaction->load(['inventoryItem', 'creator']), 201);
    }

    /**
     * Display the specified inventory transaction.
     */
    public function show(InventoryTransaction $inventoryTransaction)
    {
        return response()->json($inventoryTransaction->load(['inventoryItem.branch', 'creator']));
    }

    /**
     * Update the specified inventory transaction notes.
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
     */
    public function destroy(InventoryTransaction $inventoryTransaction)
    {
        $inventoryTransaction->delete();

        return response()->json([
            'status' => true,
            'message' => 'Inventory transaction deleted successfully.'
        ], 200);
    }
}
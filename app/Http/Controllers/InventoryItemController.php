<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryItemController extends Controller
{
    /**
     * Display a listing of inventory items.
     */
    public function index(Request $request)
    {
        $query = InventoryItem::with(['branch', 'category', 'madeFromItem']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
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

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'name', 'quantity', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

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
        $validated = $this->validatePayload($request);

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,webp,gif|max:5120']);
            $validated['image'] = $request->file('image')->store('inventory_items', 'public');
        }

        $validated['status'] = $this->deriveStatus($validated['quantity'] ?? 0, $validated['minimum_stock'] ?? 0, $validated['status'] ?? null);

        $item = InventoryItem::create($validated);

        return response()->json($item->load(['branch', 'category', 'madeFromItem']), 201);
    }

    /**
     * Display the specified inventory item.
     */
    public function show(InventoryItem $inventoryItem)
    {
        return response()->json($inventoryItem->load(['branch', 'category', 'madeFromItem', 'transactions']));
    }

    /**
     * Update the specified inventory item.
     */
    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $validated = $this->validatePayload($request, $inventoryItem);

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,webp,gif|max:5120']);
            if ($inventoryItem->image && Storage::disk('public')->exists($inventoryItem->image)) {
                Storage::disk('public')->delete($inventoryItem->image);
            }
            $validated['image'] = $request->file('image')->store('inventory_items', 'public');
        }

        $qty = $validated['quantity'] ?? $inventoryItem->quantity;
        $minStock = $validated['minimum_stock'] ?? $inventoryItem->minimum_stock;
        $validated['status'] = $this->deriveStatus($qty, $minStock, $validated['status'] ?? null);

        $inventoryItem->update($validated);

        return response()->json($inventoryItem->load(['branch', 'category', 'madeFromItem']));
    }

    /**
     * Remove the specified inventory item.
     */
    public function destroy(InventoryItem $inventoryItem)
    {
        if ($inventoryItem->image && Storage::disk('public')->exists($inventoryItem->image)) {
            Storage::disk('public')->delete($inventoryItem->image);
        }

        $inventoryItem->delete();

        return response()->json(null, 204);
    }

    /**
     * Dashboard summary cards: total items, low stock, out of stock, total stock value.
     */
    public function summary(Request $request)
    {
        $query = InventoryItem::query();

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $totalItems = (clone $query)->count();
        $lowStockItems = (clone $query)->where('status', 'low_stock')->count();
        $outOfStockItems = (clone $query)->where('status', 'out_of_stock')->count();
        $totalStockValue = (clone $query)->sum(DB::raw('quantity * purchase_price'));

        $itemsExisting7dAgo = (clone $query)->where('created_at', '<=', now()->subDays(7))->count();
        $itemsChangePercent = $itemsExisting7dAgo > 0
            ? round((($totalItems - $itemsExisting7dAgo) / $itemsExisting7dAgo) * 100, 2)
            : null;

        $itemIds = (clone $query)->pluck('id');
        $valueMovedLast7Days = InventoryTransaction::whereIn('inventory_transactions.inventory_item_id', $itemIds)
            ->where('inventory_transactions.created_at', '>=', now()->subDays(7))
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_transactions.inventory_item_id')
            ->selectRaw("SUM(CASE WHEN transaction_type IN ('purchase','restock','adjustment') THEN inventory_transactions.quantity * inventory_items.purchase_price
                         WHEN transaction_type IN ('sale','waste') THEN -inventory_transactions.quantity * inventory_items.purchase_price
                         ELSE 0 END) as net_value")
            ->value('net_value') ?? 0;

        $valueStart = $totalStockValue - $valueMovedLast7Days;
        $valueChangePercent = $valueStart > 0
            ? round((($totalStockValue - $valueStart) / $valueStart) * 100, 2)
            : null;

        return response()->json([
            'total_items' => $totalItems,
            'total_items_change_percent' => $itemsChangePercent,
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
            'total_branches' => \App\Models\Branch::count(),
            'total_stock_value' => round((float) $totalStockValue, 2),
            'total_stock_value_change_percent' => $valueChangePercent,
        ]);
    }

    /**
     * Stock alert / KPI overview analytics for a given period.
     */
    public function analytics(Request $request)
    {
        $period = $request->input('period', 'week');
        $days = match ($period) {
            'today' => 1,
            'month' => 30,
            'year' => 365,
            default => 7,
        };

        $itemQuery = InventoryItem::query();
        if ($request->filled('branch_id')) {
            $itemQuery->where('branch_id', $request->branch_id);
        }
        $itemIds = (clone $itemQuery)->pluck('id');

        $stockValue = (clone $itemQuery)->sum(DB::raw('quantity * purchase_price'));
        $lowStockAlerts = (clone $itemQuery)->where('status', 'low_stock')->count();
        $outOfStock = (clone $itemQuery)->where('status', 'out_of_stock')->count();

        $periodStart = now()->subDays($days);

        $consumedQty = InventoryTransaction::whereIn('inventory_item_id', $itemIds)
            ->whereIn('transaction_type', ['sale', 'waste'])
            ->where('created_at', '>=', $periodStart)
            ->sum('quantity');

        $consumedValue = InventoryTransaction::whereIn('inventory_transactions.inventory_item_id', $itemIds)
            ->whereIn('transaction_type', ['sale', 'waste'])
            ->where('inventory_transactions.created_at', '>=', $periodStart)
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_transactions.inventory_item_id')
            ->selectRaw('SUM(inventory_transactions.quantity * inventory_items.purchase_price) as total')
            ->value('total') ?? 0;

        $wasteQty = InventoryTransaction::whereIn('inventory_item_id', $itemIds)
            ->where('transaction_type', 'waste')
            ->where('created_at', '>=', $periodStart)
            ->sum('quantity');

        $saleQty = InventoryTransaction::whereIn('inventory_item_id', $itemIds)
            ->where('transaction_type', 'sale')
            ->where('created_at', '>=', $periodStart)
            ->sum('quantity');

        $avgQuantityOnHand = (clone $itemQuery)->avg('quantity') ?? 0;
        $inventoryTurnover = $avgQuantityOnHand > 0 ? round($consumedQty / $avgQuantityOnHand, 2) : 0;

        $topSelling = InventoryTransaction::whereIn('inventory_transactions.inventory_item_id', $itemIds)
            ->where('transaction_type', 'sale')
            ->where('inventory_transactions.created_at', '>=', $periodStart)
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_transactions.inventory_item_id')
            ->groupBy('inventory_items.id', 'inventory_items.name')
            ->selectRaw('inventory_items.id, inventory_items.name, SUM(inventory_transactions.quantity) as units_sold')
            ->orderByDesc('units_sold')
            ->first();

        return response()->json([
            'period' => $period,
            'stock_value' => round((float) $stockValue, 2),
            'inventory_turnover' => $inventoryTurnover,
            'low_stock_alerts' => $lowStockAlerts,
            'out_of_stock' => $outOfStock,
            'avg_daily_consumption' => round($consumedValue / max($days, 1), 2),
            'top_selling_product' => $topSelling ? [
                'id' => $topSelling->id,
                'name' => $topSelling->name,
                'units_sold' => (float) $topSelling->units_sold,
            ] : null,
            'damaged_wasted_items' => (float) $wasteQty,
            'sales_from_stock' => $consumedQty > 0 ? round($saleQty / $consumedQty, 2) : 0,
        ]);
    }

    /**
     * Export the (filtered) inventory item list as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = InventoryItem::with(['branch', 'category']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="inventory-items.csv"',
        ];

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'SKU', 'Category', 'Branch', 'Type', 'Quantity', 'Unit', 'Minimum Stock', 'Status', 'Purchase Price', 'Selling Price', 'Last Updated']);

            $query->orderBy('id')->chunk(500, function ($items) use ($handle) {
                foreach ($items as $item) {
                    fputcsv($handle, [
                        $item->id,
                        $item->name,
                        $item->sku,
                        $item->category?->name,
                        $item->branch?->name,
                        $item->type,
                        $item->quantity,
                        $item->unit,
                        $item->minimum_stock,
                        $item->status,
                        $item->purchase_price,
                        $item->selling_price,
                        $item->updated_at,
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Move a quantity of this item from its current branch to another branch,
     * creating the item in the target branch if it doesn't already exist there.
     */
    public function distribute(Request $request, InventoryItem $inventoryItem)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id|different:' . $inventoryItem->branch_id,
            'quantity' => 'required|numeric|min:0.01',
        ]);

        if ($validated['quantity'] > $inventoryItem->quantity) {
            return response()->json(['message' => 'Insufficient stock available to distribute.'], 422);
        }

        $target = DB::transaction(function () use ($inventoryItem, $validated, $request) {
            $inventoryItem->quantity -= $validated['quantity'];
            $inventoryItem->status = $this->deriveStatus($inventoryItem->quantity, $inventoryItem->minimum_stock);
            $inventoryItem->save();

            $targetItem = InventoryItem::firstOrCreate(
                ['branch_id' => $validated['branch_id'], 'name' => $inventoryItem->name],
                [
                    'category_id' => $inventoryItem->category_id,
                    'type' => $inventoryItem->type,
                    'unit' => $inventoryItem->unit,
                    'purchase_price' => $inventoryItem->purchase_price,
                    'selling_price' => $inventoryItem->selling_price,
                    'minimum_stock' => $inventoryItem->minimum_stock,
                    'quantity' => 0,
                    'status' => 'out_of_stock',
                ]
            );

            $targetItem->quantity += $validated['quantity'];
            $targetItem->status = $this->deriveStatus($targetItem->quantity, $targetItem->minimum_stock);
            $targetItem->save();

            $userId = $request->user()?->id;
            InventoryTransaction::create([
                'inventory_item_id' => $inventoryItem->id,
                'transaction_type' => 'adjustment',
                'quantity' => $validated['quantity'],
                'notes' => "Distributed to branch #{$validated['branch_id']}",
                'created_by' => $userId,
            ]);
            InventoryTransaction::create([
                'inventory_item_id' => $targetItem->id,
                'transaction_type' => 'adjustment',
                'quantity' => $validated['quantity'],
                'notes' => "Received from branch #{$inventoryItem->branch_id}",
                'created_by' => $userId,
            ]);

            return $targetItem;
        });

        return response()->json([
            'source_item' => $inventoryItem->fresh(['branch', 'category']),
            'target_item' => $target->load(['branch', 'category']),
        ]);
    }

    private function validatePayload(Request $request, ?InventoryItem $inventoryItem = null): array
    {
        $isUpdate = $inventoryItem !== null;
        $skuUnique = 'unique:inventory_items,sku' . ($isUpdate ? ',' . $inventoryItem->id : '');

        return $request->validate([
            'branch_id' => ($isUpdate ? 'sometimes|' : '') . 'required|exists:branches,id',
            'category_id' => ($isUpdate ? 'sometimes|' : '') . 'required|exists:inventory_categories,id',
            'type' => 'nullable|in:raw_material,prepared',
            'name' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'sku' => 'nullable|string|max:100|' . $skuUnique,
            'quantity' => ($isUpdate ? 'sometimes|' : '') . 'required|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
            'unit' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:50',
            'description' => 'nullable|string',
            'purchase_price' => ($isUpdate ? 'sometimes|' : '') . 'required|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:in_stock,low_stock,out_of_stock',
            'made_from_item_id' => 'nullable|exists:inventory_items,id',
            'pack_size' => 'nullable|numeric|min:0',
            'pack_unit' => 'nullable|string|max:50',
            'yield_qty' => 'nullable|numeric|min:0',
            'yield_unit' => 'nullable|string|max:50',
        ]);
    }

    private function deriveStatus(float $quantity, float $minimumStock, ?string $explicit = null): string
    {
        if ($explicit) {
            return $explicit;
        }

        if ($quantity <= 0) {
            return 'out_of_stock';
        }

        if ($quantity <= $minimumStock) {
            return 'low_stock';
        }

        return 'in_stock';
    }
}

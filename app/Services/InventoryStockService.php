<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    /**
     * Derive inventory item status based on quantity and minimum threshold.
     */
    public static function deriveStatus(float $quantity, float $minimumStock, ?string $explicit = null): string
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

    /**
     * Resolve required inventory item(s) and their quantities for a given menu item in a branch.
     *
     * @param int $branchId
     * @param int $menuItemId
     * @param float|int $orderQuantity
     * @return array<int, array{inventory_item_id: int, item_name: string, required_quantity: float}>
     */
    public static function resolveRequirements(int $branchId, int $menuItemId, float|int $orderQuantity): array
    {
        if ($orderQuantity <= 0) {
            return [];
        }

        $requirements = [];

        // 1. Check for branch-specific recipe first
        $recipe = Recipe::where('menu_item_id', $menuItemId)
            ->where('branch_id', $branchId)
            ->with('ingredients.inventoryItem')
            ->first();

        // 2. If no branch-specific recipe, check for a global/generic recipe
        if (!$recipe) {
            $recipe = Recipe::where('menu_item_id', $menuItemId)
                ->with('ingredients.inventoryItem')
                ->first();
        }

        if ($recipe && $recipe->ingredients->isNotEmpty()) {
            foreach ($recipe->ingredients as $ingredient) {
                $invItem = $ingredient->inventoryItem;
                if (!$invItem) {
                    continue;
                }

                $targetInventoryItem = null;

                // If ingredient's inventory item is already in this branch
                if ((int) $invItem->branch_id === (int) $branchId) {
                    $targetInventoryItem = $invItem;
                } else {
                    // Look up matching inventory item in the branch by name or SKU
                    $targetInventoryItem = InventoryItem::where('branch_id', $branchId)
                        ->where(function ($q) use ($invItem) {
                            $q->where('name', $invItem->name);
                            if (!empty($invItem->sku)) {
                                $q->orWhere('sku', $invItem->sku);
                            }
                        })
                        ->first();
                }

                if ($targetInventoryItem) {
                    $neededQty = (float) $ingredient->quantity * (float) $orderQuantity;
                    $requirements[] = [
                        'inventory_item_id' => $targetInventoryItem->id,
                        'item_name' => $targetInventoryItem->name,
                        'required_quantity' => $neededQty,
                    ];
                }
            }
        } else {
            // 3. If no recipe exists, check if there is a direct InventoryItem in the branch with the same name or SKU
            $menuItem = MenuItem::find($menuItemId);
            if ($menuItem) {
                $directItem = InventoryItem::where('branch_id', $branchId)
                    ->where(function ($q) use ($menuItem) {
                        $q->where('name', $menuItem->name);
                        if (!empty($menuItem->slug)) {
                            $q->orWhere('sku', $menuItem->slug);
                        }
                    })
                    ->first();

                if ($directItem) {
                    $requirements[] = [
                        'inventory_item_id' => $directItem->id,
                        'item_name' => $directItem->name,
                        'required_quantity' => (float) $orderQuantity,
                    ];
                }
            }
        }

        return $requirements;
    }

    /**
     * Validate branch stock for an array of ordered items before order placement/cart addition.
     *
     * @param int|null $branchId
     * @param array $items Array of items with 'menu_item_id' and 'quantity'
     * @throws ValidationException
     */
    public static function validateStockForItems(?int $branchId, array $items): void
    {
        if (empty($branchId) || empty($items)) {
            return;
        }

        // Aggregate total required quantities by inventory_item_id
        $aggregated = [];
        $itemNames = [];

        foreach ($items as $item) {
            $menuItemId = $item['menu_item_id'] ?? null;
            $quantity = (float) ($item['quantity'] ?? 1);

            if (!$menuItemId || $quantity <= 0) {
                continue;
            }

            $reqs = self::resolveRequirements($branchId, (int) $menuItemId, $quantity);

            foreach ($reqs as $req) {
                $invId = $req['inventory_item_id'];
                $aggregated[$invId] = ($aggregated[$invId] ?? 0.0) + $req['required_quantity'];
                $itemNames[$invId] = $req['item_name'];
            }
        }

        if (empty($aggregated)) {
            return;
        }

        // Check stock availability
        $inventoryItems = InventoryItem::whereIn('id', array_keys($aggregated))->get()->keyBy('id');

        foreach ($aggregated as $invId => $requiredQty) {
            $invItem = $inventoryItems->get($invId);
            $availableQty = $invItem ? (float) $invItem->quantity : 0.0;
            $itemName = $itemNames[$invId] ?? ($invItem?->name ?? "Item #{$invId}");

            if (!$invItem || $availableQty < $requiredQty) {
                throw ValidationException::withMessages([
                    'stock' => [
                        "Insufficient stock for '{$itemName}' in this branch. Available: {$availableQty}, Required: {$requiredQty}."
                    ]
                ]);
            }
        }
    }

    /**
     * Deduct stock for an order upon completion.
     * This method is idempotent — if stock was already deducted for this order, it will not deduct again.
     *
     * @param Order $order
     * @param int|null $userId
     * @return bool Returns true if stock was deducted, false if already deducted or no stock tracking needed.
     */
    public static function deductStockForOrder(Order $order, ?int $userId = null): bool
    {
        if (!$order->branch_id) {
            return false;
        }

        // Idempotency check: has stock already been deducted for this order?
        $alreadyDeducted = InventoryTransaction::where('transaction_type', 'sale')
            ->where('notes', 'like', "%Order #{$order->order_number}%")
            ->exists();

        if ($alreadyDeducted) {
            Log::info("Stock already deducted for Order #{$order->order_number}. Skipping duplicate deduction.");
            return false;
        }

        // Load items if not already loaded
        if (!$order->relationLoaded('items')) {
            $order->load('items');
        }

        if ($order->items->isEmpty()) {
            return false;
        }

        // Aggregate total required quantities by inventory_item_id
        $aggregated = [];
        $itemNames = [];

        foreach ($order->items as $orderItem) {
            $reqs = self::resolveRequirements($order->branch_id, (int) $orderItem->menu_item_id, (float) $orderItem->quantity);

            foreach ($reqs as $req) {
                $invId = $req['inventory_item_id'];
                $aggregated[$invId] = ($aggregated[$invId] ?? 0.0) + $req['required_quantity'];
                $itemNames[$invId] = $req['item_name'];
            }
        }

        if (empty($aggregated)) {
            return false;
        }

        // Deduct inventory inside transaction with pessimistic locking
        foreach ($aggregated as $invId => $requiredQty) {
            $item = InventoryItem::where('id', $invId)->lockForUpdate()->first();
            if (!$item) {
                continue;
            }

            $newQuantity = round($item->quantity - $requiredQty, 4);
            $item->quantity = $newQuantity;
            $item->status = self::deriveStatus($item->quantity, (float) $item->minimum_stock);
            $item->save();

            InventoryTransaction::create([
                'inventory_item_id' => $item->id,
                'transaction_type' => 'sale',
                'quantity' => $requiredQty,
                'notes' => "Deducted on Order #{$order->order_number} completion",
                'created_by' => $userId ?? $order->user_id,
            ]);
        }

        return true;
    }
}

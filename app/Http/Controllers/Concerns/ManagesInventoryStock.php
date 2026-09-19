<?php

namespace App\Http\Controllers\Concerns;

use App\Models\InventoryItem;

/**
 * Put this file at: app/Http/Controllers/Concerns/ManagesInventoryStock.php
 *
 * Single source of truth for:
 *  - status thresholds (previously duplicated 3x across controllers and could drift)
 *  - applying a signed quantity delta to an item safely
 *
 * IMPORTANT: applyStockDelta() now LOCKS the row (lockForUpdate) and THROWS
 * instead of silently clamping to 0 when stock would go negative. Every
 * caller MUST already be inside a DB::transaction() — the lock is only
 * meaningful there, and the thrown exception is meant to roll the whole
 * transaction back.
 */
trait ManagesInventoryStock
{
    protected function deriveStatus(float $quantity, float $minimumStock, ?string $explicit = null): string
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

    protected function applyStockDelta(int $inventoryItemId, float $delta): InventoryItem
    {
        $item = InventoryItem::where('id', $inventoryItemId)->lockForUpdate()->firstOrFail();

        $newQuantity = round($item->quantity + $delta, 4);

        if ($newQuantity < 0) {
            throw new \RuntimeException(
                "Insufficient stock for \"{$item->name}\" (has {$item->quantity}, tried to move " . abs($delta) . ')'
            );
        }

        $item->quantity = $newQuantity;
        $item->status = $this->deriveStatus($item->quantity, $item->minimum_stock);
        $item->save();

        return $item;
    }
}
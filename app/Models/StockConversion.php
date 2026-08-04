<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockConversion extends Model
{
    use HasFactory;

    protected $table = 'stock_conversions';

    protected $fillable = ['branch_id', 'inventory_item_id', 'converted_item_id', 'quantity', 'created_by'];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function convertedItem()
    {
        return $this->belongsTo(InventoryItem::class, 'converted_item_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query to filter conversions by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        if (empty($branchId)) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope query to filter conversions by source or target inventory item.
     */
    public function scopeForInventoryItem($query, $itemId)
    {
        if (empty($itemId)) {
            return $query;
        }

        return $query->where(function ($q) use ($itemId) {
            $q->where('inventory_item_id', $itemId)
                ->orWhere('converted_item_id', $itemId);
        });
    }
}
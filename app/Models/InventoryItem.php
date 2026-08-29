<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    protected $table = 'inventory_items';

    protected $fillable = [
        'branch_id', 'category_id', 'type', 'name', 'sku', 'quantity', 'minimum_stock',
        'unit', 'image', 'description', 'purchase_price', 'selling_price', 'status',
        'made_from_item_id', 'pack_size', 'pack_unit', 'yield_qty', 'yield_unit',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        return str_starts_with($this->image, 'http') || str_starts_with($this->image, '/')
            ? $this->image
            : asset('storage/' . $this->image);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function madeFromItem()
    {
        return $this->belongsTo(InventoryItem::class, 'made_from_item_id');
    }

    public function preparedItems()
    {
        return $this->hasMany(InventoryItem::class, 'made_from_item_id');
    }

    /**
     * Compute the produced quantity and unit for converting a number of packs
     * of this item's raw material into this (prepared) item, using its stored ratio.
     */
    public function conversionYieldFor(float $packs): array
    {
        return [
            'raw_consumed' => (float) $this->pack_size * $packs,
            'raw_unit' => $this->pack_unit,
            'yield_produced' => (float) $this->yield_qty * $packs,
            'yield_unit' => $this->yield_unit,
        ];
    }
}
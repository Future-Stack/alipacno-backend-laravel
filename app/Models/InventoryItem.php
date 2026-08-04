<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    protected $table = 'inventory_items';

    protected $fillable = ['branch_id', 'category_id', 'name', 'sku', 'quantity', 'minimum_stock', 'unit', 'purchase_price', 'selling_price', 'status'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class);
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
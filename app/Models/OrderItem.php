<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'item_name',
        'size_id',
        'size_name',
        'cooking_preference_id',
        'cooking_preference',
        'spice_level_id',
        'spice_level',
        'quantity',
        'unit_price',
        'subtotal',
        'special_instructions',
        'options_summary',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function size()
    {
        return $this->belongsTo(ItemSize::class, 'size_id');
    }

    public function cookingPreference()
    {
        return $this->belongsTo(CookingPreference::class, 'cooking_preference_id');
    }

    public function spiceLevel()
    {
        return $this->belongsTo(SpiceLevel::class, 'spice_level_id');
    }

    public function toppings()
    {
        return $this->hasMany(OrderItemTopping::class);
    }
}
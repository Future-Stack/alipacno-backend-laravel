<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $table = 'cart_items';

    protected $fillable = ['cart_id','admin_id','menu_item_id', 'size_id', 'cooking_preference_id', 'spice_level_id', 'quantity', 'unit_price', 'total_price', 'special_instructions'];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
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
        return $this->hasMany(CartItemTopping::class);
    }
}

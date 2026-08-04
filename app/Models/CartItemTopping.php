<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItemTopping extends Model
{
    use HasFactory;

    protected $table = 'cart_item_toppings';

    protected $fillable = ['cart_item_id', 'topping_id', 'price'];

    public function cartItem()
    {
        return $this->belongsTo(CartItem::class);
    }

    public function topping()
    {
        return $this->belongsTo(Topping::class);
    }
}
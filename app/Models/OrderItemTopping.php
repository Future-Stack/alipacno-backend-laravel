<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItemTopping extends Model
{
    use HasFactory;

    protected $table = 'order_item_toppings';

    protected $fillable = ['order_item_id', 'topping_name', 'price'];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
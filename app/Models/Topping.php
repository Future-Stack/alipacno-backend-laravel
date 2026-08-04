<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topping extends Model
{
    use HasFactory;

    protected $table = 'toppings';

    protected $fillable = ['restaurant_id', 'menu_item_id', 'name', 'price'];

    protected $casts = [
        'price' => 'float',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id');
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    /**
     * Scope query to filter toppings by restaurant.
     */
    public function scopeForRestaurant($query, $restaurantId)
    {
        if (empty($restaurantId)) {
            return $query;
        }

        return $query->where('restaurant_id', $restaurantId);
    }

    /**
     * Scope query to filter toppings by menu item.
     */
    public function scopeForMenuItem($query, $menuItemId)
    {
        if (empty($menuItemId)) {
            return $query;
        }

        return $query->where('menu_item_id', $menuItemId);
    }

    /**
     * Scope query to search topping name.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where('name', 'like', "%{$search}%");
    }
}
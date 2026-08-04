<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpiceLevel extends Model
{
    use HasFactory;

    protected $table = 'spice_levels';

    protected $fillable = ['restaurant_id', 'menu_item_id', 'name'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id');
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    /**
     * Scope query to filter spice levels by restaurant.
     */
    public function scopeForRestaurant($query, $restaurantId)
    {
        if (empty($restaurantId)) {
            return $query;
        }

        return $query->where('restaurant_id', $restaurantId);
    }

    /**
     * Scope query to filter spice levels by menu item.
     */
    public function scopeForMenuItem($query, $menuItemId)
    {
        if (empty($menuItemId)) {
            return $query;
        }

        return $query->where('menu_item_id', $menuItemId);
    }

    /**
     * Scope query to search spice level name.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where('name', 'like', "%{$search}%");
    }
}
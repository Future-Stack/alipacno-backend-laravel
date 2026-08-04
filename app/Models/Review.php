<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $table = 'reviews';

    protected $fillable = ['user_id', 'restaurant_id', 'menu_item_id', 'order_id', 'rating', 'review'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope query to search review text.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where('review', 'like', "%{$search}%");
    }

    /**
     * Scope query to filter reviews by restaurant.
     */
    public function scopeForRestaurant($query, $restaurantId)
    {
        if (empty($restaurantId)) {
            return $query;
        }

        return $query->where('restaurant_id', $restaurantId);
    }

    /**
     * Scope query to filter reviews by menu item.
     */
    public function scopeForMenuItem($query, $menuItemId)
    {
        if (empty($menuItemId)) {
            return $query;
        }

        return $query->where('menu_item_id', $menuItemId);
    }

    /**
     * Scope query to filter reviews by rating score.
     */
    public function scopeByRating($query, $rating)
    {
        if (empty($rating)) {
            return $query;
        }

        return $query->where('rating', $rating);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableReservation extends Model
{
    use HasFactory;

    protected $table = 'table_reservations';

    protected $fillable = ['restaurant_id', 'table_id', 'user_id', 'reservation_date', 'reservation_time', 'guest_count', 'status'];

    protected $casts = [
        'reservation_date' => 'date:Y-m-d',
        'guest_count' => 'integer',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id');
    }

    public function table()
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope query to filter reservations by restaurant.
     */
    public function scopeForRestaurant($query, $restaurantId)
    {
        if (empty($restaurantId)) {
            return $query;
        }

        return $query->where('restaurant_id', $restaurantId);
    }

    /**
     * Scope query to filter reservations by table.
     */
    public function scopeForTable($query, $tableId)
    {
        if (empty($tableId)) {
            return $query;
        }

        return $query->where('table_id', $tableId);
    }

    /**
     * Scope query to filter reservations by user.
     */
    public function scopeForUser($query, $userId)
    {
        if (empty($userId)) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    /**
     * Scope query to filter reservations by status.
     */
    public function scopeByStatus($query, $status)
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope query to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->whereDate('reservation_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('reservation_date', '<=', $endDate);
        }

        return $query;
    }
}
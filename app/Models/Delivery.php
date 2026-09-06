<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use HasFactory;

    protected $table = 'deliveries';

    protected $fillable = [
        'order_id',
        'driver_id',
        'driver_shift_id',
        'delivery_status',
        'pickup_time',
        'delivered_time',
        'estimated_time',
        'distance_miles',
        'driver_fee',
        'is_cod',
        'cash_collected',
    ];

    protected $casts = [
        'distance_miles' => 'float',
        'driver_fee' => 'float',
        'is_cod' => 'boolean',
        'cash_collected' => 'float',
        'pickup_time' => 'datetime',
        'delivered_time' => 'datetime',
        'estimated_time' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function shift()
    {
        return $this->belongsTo(DriverShift::class, 'driver_shift_id');
    }

    public function user()
    {
        return $this->hasOneThrough(User::class, Order::class, 'id', 'id', 'order_id', 'user_id');
    }
}

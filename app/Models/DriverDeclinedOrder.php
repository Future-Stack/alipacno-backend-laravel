<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverDeclinedOrder extends Model
{
    use HasFactory;

    protected $table = 'driver_declined_orders';

    protected $fillable = [
        'driver_id',
        'order_id',
        'reason',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

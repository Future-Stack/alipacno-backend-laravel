<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KitchenOrder extends Model
{
    use HasFactory;

    protected $table = 'kitchen_orders';

    protected $fillable = ['order_id', 'kitchen_station_id', 'chef_id', 'status', 'started_at', 'ready_at', 'completed_at'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function station()
    {
        return $this->belongsTo(KitchenStation::class);
    }
}
<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, LogsActivity, Auditable;

    protected $table = 'orders';

    protected $fillable = [
        'order_number',
        'user_id',
        'restaurant_id',
        'branch_id',
        'address_id',
        'table_id',
        'reservation_id',
        'order_type',
        'order_status',
        'payment_status',
        'payment_method',
        'order_source',
        'assigned_staff_id',
        'assigned_driver_id',
        'subtotal',
        'vat',
        'delivery_fee',
        'discount',
        'tip',
        'rider_tip',
        'total',
        'loyalty_points',
        'loyalty_points_earned',
        'loyalty_points_used',
        'estimated_delivery_time',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedStaff()
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id');
    }

    public function assignedDriver()
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function kitchenOrders()
    {
        return $this->hasMany(KitchenOrder::class);
    }
}
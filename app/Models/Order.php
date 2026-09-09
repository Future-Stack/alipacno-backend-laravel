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
        'rejection_reason',
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

    public function address()
    {
        return $this->belongsTo(UserAddress::class, 'address_id');
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

    public function callLogs()
    {
        return $this->hasMany(CallLog::class, 'order_id');
    }

    /**
     * Calculate dynamic distance in KM between Branch and Delivery Address using Haversine formula.
     */
    public function calculateDistanceKm(): float
    {
        $branchLat = (float) ($this->branch?->latitude ?? 0);
        $branchLon = (float) ($this->branch?->longitude ?? 0);

        $address = $this->address;
        $custLat = (float) ($address?->latitude ?? 0);
        $custLon = (float) ($address?->longitude ?? 0);

        if ($branchLat != 0 && $branchLon != 0 && $custLat != 0 && $custLon != 0) {
            $earthRadius = 6371; // km
            $dLat = deg2rad($custLat - $branchLat);
            $dLon = deg2rad($custLon - $branchLon);
            $a = sin($dLat / 2) * sin($dLat / 2) +
                cos(deg2rad($branchLat)) * cos(deg2rad($custLat)) *
                sin($dLon / 2) * sin($dLon / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            return round($earthRadius * $c, 1);
        }

        return 2.5; // Standard city radius fallback if coordinates not pinned
    }

    /**
     * Calculate dynamic remaining delivery time in minutes.
     */
    public function calculateRemainingMinutes(): int
    {
        if ($this->estimated_delivery_time) {
            $diff = now()->diffInMinutes(\Carbon\Carbon::parse($this->estimated_delivery_time), false);
            if ($diff > 0) {
                return (int) $diff;
            }
        }

        // Estimate based on distance (approx 20 km/h city motorcycle speed + 5 min prep/buffer)
        $distance = $this->calculateDistanceKm();
        return (int) max(5, ceil(($distance / 20) * 60) + 5);
    }
}
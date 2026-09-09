<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverPayout extends Model
{
    use HasFactory;

    protected $table = 'driver_payouts';

    protected $fillable = [
        'driver_id',
        'year',
        'week_number',
        'start_date',
        'end_date',
        'hours_worked',
        'hourly_rate',
        'hourly_earnings',
        'delivery_fees',
        'tips',
        'gross_earnings',
        'cash_collected',
        'net_payout',
        'status',
        'stripe_payout_id',
        'stripe_transfer_id',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'hours_worked' => 'float',
        'hourly_rate' => 'float',
        'hourly_earnings' => 'float',
        'delivery_fees' => 'float',
        'tips' => 'float',
        'gross_earnings' => 'float',
        'cash_collected' => 'float',
        'net_payout' => 'float',
        'paid_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}

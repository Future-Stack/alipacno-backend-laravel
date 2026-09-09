<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffPayout extends Model
{
    use HasFactory;

    protected $table = 'staff_payouts';

    protected $fillable = [
        'staff_id',
        'year',
        'week_number',
        'start_date',
        'end_date',
        'hours_worked',
        'hourly_rate',
        'gross_earnings',
        'net_payout',
        'status',
        'stripe_transfer_id',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'hours_worked' => 'float',
        'hourly_rate' => 'float',
        'gross_earnings' => 'float',
        'net_payout' => 'float',
        'paid_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}

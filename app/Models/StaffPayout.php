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
        'branch_id',
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
        'approved_at',
        'approved_by',
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
        'approved_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendance()
    {
        return $this->hasMany(StaffAttendance::class, 'payout_id');
    }
}

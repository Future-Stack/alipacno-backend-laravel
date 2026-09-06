<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverShift extends Model
{
    use HasFactory;

    protected $table = 'driver_shifts';

    protected $fillable = [
        'driver_id',
        'branch_id',
        'clock_in_at',
        'clock_out_at',
        'total_hours',
        'completed_drops',
        'total_distance_miles',
        'status',
        'notes',
    ];

    protected $casts = [
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'total_hours' => 'float',
        'completed_drops' => 'integer',
        'total_distance_miles' => 'float',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }
}

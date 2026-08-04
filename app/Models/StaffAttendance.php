<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffAttendance extends Model
{
    use HasFactory;

    protected $table = 'staff_attendance';

    protected $fillable = ['staff_id', 'clock_in', 'clock_out', 'total_hours', 'status'];

    protected $casts = [
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'total_hours' => 'float',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * Scope query to filter attendance by staff.
     */
    public function scopeForStaff($query, $staffId)
    {
        if (empty($staffId)) {
            return $query;
        }

        return $query->where('staff_id', $staffId);
    }

    /**
     * Scope query to filter attendance by status.
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
            $query->whereDate('clock_in', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('clock_in', '<=', $endDate);
        }

        return $query;
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'employee_id',
        'user_id',
        'branch_id',
        'name',
        'image',
        'email',
        'phone',
        'role_id',
        'shift',
        'time_in',
        'time_out',
        'salary',
        'salary_type',
        'hourly_rate',
        'commission',
        'hire_date',
        'status',
        'stripe_account_id',
        'stripe_onboarding_completed',
    ];

    protected $appends = [
        'hours_worked',
        'image_url',
        'is_driver',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'commission' => 'decimal:2',
        'hire_date' => 'date:Y-m-d',
        'time_in' => 'datetime:H:i',
        'time_out' => 'datetime:H:i',
    ];

    /**
     * Branch relationship.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Role relationship.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Attendance relationship.
     */
    public function attendances()
    {
        return $this->hasMany(StaffAttendance::class);
    }

    /**
     * Calculate total working hours from time_in and time_out.
     *
     * Example:
     * 08:00 -> 17:00 = 09:00
     * 20:00 -> 04:00 = 08:00
     */
    protected function hoursWorked(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->time_in || !$this->time_out) {
                    return null;
                }

                $timeIn = $this->time_in->copy();
                $timeOut = $this->time_out->copy();

                // Overnight shift
                if ($timeOut->lessThan($timeIn)) {
                    $timeOut->addDay();
                }

                $minutes = $timeIn->diffInMinutes($timeOut);

                $hours = intdiv($minutes, 60);
                $remainingMinutes = $minutes % 60;

                return sprintf(
                    '%02d:%02d',
                    $hours,
                    $remainingMinutes
                );
            }
        );
    }

    /**
     * Get full image URL.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->image) {
                    return null;
                }

                return asset('storage/' . $this->image);
            }
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Driver relationship (linked by staff_id or user_id fallback).
     */
    public function driver()
    {
        return $this->hasOne(Driver::class, 'staff_id');
    }

    /**
     * Check whether this staff member is a Driver.
     */
    protected function isDriver(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->relationLoaded('driver')) {
                    return (bool) $this->driver;
                }
                if ($this->relationLoaded('role') && $this->role && str_contains(strtolower($this->role->name), 'driver')) {
                    return true;
                }
                return $this->driver()->exists();
            }
        );
    }
}

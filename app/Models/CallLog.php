<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    use HasFactory;

    protected $table = 'call_logs';

    protected $fillable = ['branch_id', 'user_id', 'staff_id', 'order_id', 'customer_name', 'phone', 'postcode', 'call_sid', 'call_type', 'call_status', 'call_duration', 'call_outcome', 'notes', 'recording_url', 'started_at', 'ended_at'];

    protected $casts = [
        'call_duration' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected $appends = [
        'duration_formatted',
        'formatted_time',
        'formatted_date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Accessor for formatted duration (e.g. "04:12" or "01:23:45").
     */
    public function getDurationFormattedAttribute(): string
    {
        $seconds = (int) ($this->call_duration ?? 0);
        if ($seconds < 0) {
            $seconds = 0;
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * Accessor for formatted call time (e.g. "08:42 PM").
     */
    public function getFormattedTimeAttribute(): ?string
    {
        return $this->started_at ? $this->started_at->format('h:i A') : ($this->created_at ? $this->created_at->format('h:i A') : null);
    }

    /**
     * Accessor for formatted call date (e.g. "May 04, 2026").
     */
    public function getFormattedDateAttribute(): ?string
    {
        return $this->started_at ? $this->started_at->format('M d, Y') : ($this->created_at ? $this->created_at->format('M d, Y') : null);
    }

    /**
     * Scope query to filter call logs by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        if (empty($branchId)) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope query to filter call logs by user.
     */
    public function scopeByUser($query, $userId)
    {
        if (empty($userId)) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    /**
     * Scope query to filter call logs by staff.
     */
    public function scopeByStaff($query, $staffId)
    {
        if (empty($staffId)) {
            return $query;
        }

        return $query->where('staff_id', $staffId);
    }

    /**
     * Scope query to filter call logs by call type.
     */
    public function scopeByType($query, $callType)
    {
        if (empty($callType)) {
            return $query;
        }

        return $query->where('call_type', $callType);
    }

    /**
     * Scope query to filter call logs by status.
     */
    public function scopeByStatus($query, $callStatus)
    {
        if (empty($callStatus)) {
            return $query;
        }

        return $query->where('call_status', $callStatus);
    }

    /**
     * Scope query to filter call logs by outcome.
     */
    public function scopeByOutcome($query, $callOutcome)
    {
        if (empty($callOutcome)) {
            return $query;
        }

        return $query->where('call_outcome', $callOutcome);
    }

    /**
     * Scope query for converted calls.
     */
    public function scopeConverted($query)
    {
        return $query->where(function ($q) {
            $q->where('call_outcome', 'converted')
              ->orWhereNotNull('order_id');
        });
    }

    /**
     * Scope query for missed calls.
     */
    public function scopeMissed($query)
    {
        return $query->where('call_status', 'missed');
    }

    /**
     * Scope query for answered calls.
     */
    public function scopeAnswered($query)
    {
        return $query->where('call_status', 'answered');
    }

    /**
     * Scope query for date range.
     */
    public function scopeDateRange($query, $from, $to)
    {
        if (!empty($from) && !empty($to)) {
            return $query->whereBetween('started_at', [$from, $to]);
        } elseif (!empty($from)) {
            return $query->where('started_at', '>=', $from);
        } elseif (!empty($to)) {
            return $query->where('started_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope query to search call logs.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('phone', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('call_sid', 'like', "%{$search}%")
                ->orWhere('postcode', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%")
                ->orWhereHas('order', function ($oq) use ($search) {
                    $oq->where('order_number', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('customer_phone', 'like', "%{$search}%");
                })
                ->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
        });
    }
}
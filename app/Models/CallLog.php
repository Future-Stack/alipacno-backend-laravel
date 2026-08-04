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
                ->orWhere('notes', 'like', "%{$search}%");
        });
    }
}
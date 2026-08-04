<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected $fillable = ['branch_id', 'user_type', 'user_id', 'action', 'module', 'ip_address'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Scope query to filter logs by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        if (empty($branchId)) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope query to filter logs by user.
     */
    public function scopeByUser($query, $userId)
    {
        if (empty($userId)) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    /**
     * Scope query to filter logs by module.
     */
    public function scopeByModule($query, $module)
    {
        if (empty($module)) {
            return $query;
        }

        return $query->where('module', $module);
    }

    /**
     * Scope query to filter logs by action.
     */
    public function scopeByAction($query, $action)
    {
        if (empty($action)) {
            return $query;
        }

        return $query->where('action', $action);
    }

    /**
     * Scope query to search action, module, user_type, or IP address.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('action', 'like', "%{$search}%")
                ->orWhere('module', 'like', "%{$search}%")
                ->orWhere('user_type', 'like', "%{$search}%")
                ->orWhere('ip_address', 'like', "%{$search}%");
        });
    }
}
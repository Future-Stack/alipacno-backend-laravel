<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_type',
        'user_id',
        'module',
        'action',
        'module_id',
        'old_data',
        'new_data',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope query to filter by module.
     */
    public function scopeByModule($query, $module)
    {
        if (empty($module)) {
            return $query;
        }

        return $query->where('module', $module);
    }

    /**
     * Scope query to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        if (empty($userId)) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    /**
     * Scope query to filter by action.
     */
    public function scopeByAction($query, $action)
    {
        if (empty($action)) {
            return $query;
        }

        return $query->where('action', $action);
    }

    /**
     * Scope query to search action, module, user_type, IP or user agent.
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
                ->orWhere('ip_address', 'like', "%{$search}%")
                ->orWhere('user_agent', 'like', "%{$search}%");
        });
    }
}
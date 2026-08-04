<?php

namespace App\Traits;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            self::recordActivity('created', $model);
        });

        static::updated(function ($model) {
            self::recordActivity('updated', $model);
        });

        static::deleted(function ($model) {
            self::recordActivity('deleted', $model);
        });
    }

    protected static function recordActivity($action, $model)
    {
        // Prevent infinite recursion loops for logging tables
        if ($model instanceof ActivityLog || $model instanceof AuditLog) {
            return;
        }

        $user = Auth::user();

        ActivityLog::create([
            'branch_id' => $model->branch_id ?? null,
            'user_type' => $user ? ($user->user_type ?? 'user') : null,
            'user_id'   => $user ? $user->id : ($model->user_id ?? null),
            'action'    => $action,
            'module'    => class_basename($model),
            'ip_address' => Request::ip(),
        ]);
    }
}

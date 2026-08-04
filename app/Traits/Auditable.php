<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            self::audit('created', null, $model->getAttributes(), $model);
        });

        static::updated(function ($model) {
            $oldData = array_intersect_key($model->getOriginal(), $model->getChanges());
            $newData = $model->getChanges();

            unset($oldData['updated_at'], $newData['updated_at']);

            if (!empty($newData)) {
                self::audit('updated', $oldData, $newData, $model);
            }
        });

        static::deleted(function ($model) {
            self::audit('deleted', $model->getAttributes(), null, $model);
        });
    }

    protected static function audit($action, $oldData, $newData, $model)
    {
        // Prevent infinite recursion loops for audit & activity log models
        if ($model instanceof \App\Models\AuditLog || $model instanceof \App\Models\ActivityLog) {
            return;
        }

        $user = Auth::user();

        AuditLog::create([
            'user_type'  => $user ? get_class($user) : null,
            'user_id'    => $user ? $user->id : ($model->user_id ?? null),
            'module'     => class_basename($model),
            'module_id'  => $model->id ?? null,
            'action'     => $action,
            'old_data'   => $oldData,
            'new_data'   => $newData,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
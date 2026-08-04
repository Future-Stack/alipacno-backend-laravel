<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';

    protected $fillable = ['name', 'module'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    /**
     * Scope query to filter permissions by search term.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('module', 'like', "%{$search}%");
        });
    }

    /**
     * Scope query to filter permissions by module.
     */
    public function scopeByModule($query, $module)
    {
        if (empty($module)) {
            return $query;
        }

        return $query->where('module', $module);
    }
}
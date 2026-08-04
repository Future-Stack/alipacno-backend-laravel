<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    protected $fillable = ['setting_key', 'setting_value', 'description', 'updated_by'];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope query to filter by setting_key.
     */
    public function scopeByKey($query, $key)
    {
        return $query->where('setting_key', $key);
    }

    /**
     * Scope query to search setting key, value, or description.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('setting_key', 'like', "%{$search}%")
                ->orWhere('setting_value', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
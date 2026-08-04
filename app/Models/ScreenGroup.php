<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreenGroup extends Model
{
    use HasFactory;

    protected $table = 'screen_groups';

    protected $fillable = ['branch_id', 'name', 'description'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function screens()
    {
        return $this->belongsToMany(DigitalScreen::class, 'screen_group_screens', 'screen_group_id', 'screen_id');
    }

    /**
     * Scope query to search screen groups by name or description.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope query to filter screen groups by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        if (empty($branchId)) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }
}
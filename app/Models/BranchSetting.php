<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchSetting extends Model
{
    use HasFactory;

    protected $table = 'branch_settings';

    protected $fillable = ['branch_id', 'tax_rate', 'minimum_order', 'delivery_radius', 'opening_time', 'closing_time', 'currency', 'timezone'];

    protected $casts = [
        'tax_rate' => 'float',
        'minimum_order' => 'float',
        'delivery_radius' => 'float',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Scope query to filter settings by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        if (empty($branchId)) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }
}
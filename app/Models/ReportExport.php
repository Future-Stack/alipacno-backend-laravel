<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportExport extends Model
{
    use HasFactory;

    protected $table = 'report_exports';

    protected $fillable = [
        'branch_id',
        'report_name',
        'exported_by',
        'format',
        'file',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function exporter()
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    /**
     * Scope query to search report exports by name or file path.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('report_name', 'like', "%{$search}%")
              ->orWhere('file', 'like', "%{$search}%");
        });
    }

    /**
     * Scope query to filter report exports by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        if (empty($branchId)) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope query to filter report exports by format.
     */
    public function scopeByFormat($query, $format)
    {
        if (empty($format)) {
            return $query;
        }

        return $query->where('format', strtolower($format));
    }
}
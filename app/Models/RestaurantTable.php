<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantTable extends Model
{
    use HasFactory;

    protected $table = 'restaurant_tables';

    protected $fillable = ['restaurant_id', 'branch_id', 'table_number', 'capacity', 'qr_code', 'status'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function reservations()
    {
        return $this->hasMany(TableReservation::class, 'table_id');
    }

    /**
     * Scope query to search restaurant tables by number or QR code.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('table_number', 'like', "%{$search}%")
              ->orWhere('qr_code', 'like', "%{$search}%");
        });
    }

    /**
     * Scope query to filter restaurant tables by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        if (empty($branchId)) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope query to filter restaurant tables by status.
     */
    public function scopeByStatus($query, $status)
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope query to get available tables only.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
}
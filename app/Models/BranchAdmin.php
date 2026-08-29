<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchAdmin extends Model
{
    use HasFactory;

    protected $table = 'branch_admins';

    protected $fillable = ['user_id', 'branch_id', 'name', 'email', 'phone', 'password', 'avatar', 'last_login', 'status'];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'hashed',
        'last_login' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Scope query to filter active branch admins.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to filter branch admins by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        if (empty($branchId)) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope query to search admin name, email, or phone.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }
}
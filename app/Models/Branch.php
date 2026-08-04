<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory, LogsActivity, Auditable;

    protected $table = 'branches';

    protected $fillable = ['restaurant_id', 'name', 'branch_code', 'phone', 'email', 'address', 'city', 'postcode', 'postal_code', 'latitude', 'longitude', 'opening_time', 'closing_time', 'is_active', 'status'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function settings()
    {
        return $this->hasOne(BranchSetting::class);
    }
}
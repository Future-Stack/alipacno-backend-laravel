<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryArea extends Model
{
    use HasFactory;

    protected $table = 'delivery_areas';

    protected $fillable = ['restaurant_id', 'branch_id', 'postcode', 'delivery_fee', 'minimum_order', 'estimated_delivery_time', 'is_active'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
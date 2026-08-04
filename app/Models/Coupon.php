<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $table = 'coupons';

    protected $fillable = ['restaurant_id', 'code', 'discount_type', 'discount', 'minimum_order', 'expiry_date', 'status'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
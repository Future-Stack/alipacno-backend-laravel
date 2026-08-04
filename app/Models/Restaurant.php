<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory, LogsActivity, Auditable;

    protected $table = 'restaurants';

    protected $fillable = ['name', 'slug', 'logo', 'cover_image', 'phone', 'email', 'address', 'postcode', 'latitude', 'longitude', 'delivery_radius', 'opening_time', 'closing_time', 'is_delivery', 'is_collection', 'is_dine_in', 'is_table_order', 'status'];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function tables()
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function deliveryAreas()
    {
        return $this->hasMany(DeliveryArea::class);
    }
}
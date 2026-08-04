<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, LogsActivity, Auditable;

    protected $table = 'categories';

    protected $fillable = ['restaurant_id', 'name', 'slug', 'icon', 'image', 'sort_order', 'is_active', 'status'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcategory extends Model
{
    protected $guarded = [];

    public function getRouteKeyName()
    {
        return 'slug';
    }
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}

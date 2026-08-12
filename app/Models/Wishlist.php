<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $guarded = [];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}

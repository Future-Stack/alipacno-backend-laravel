<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemSize extends Model
{
    use HasFactory;

    protected $table = 'item_sizes';

    protected $fillable = ['menu_item_id', 'name', 'size_description', 'extra_price'];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
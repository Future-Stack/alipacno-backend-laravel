<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KitchenStation extends Model
{
    use HasFactory;

    protected $table = 'kitchen_stations';

    protected $fillable = ['branch_id', 'name', 'description', 'display_order', 'status'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function orders()
    {
        return $this->hasMany(KitchenOrder::class);
    }
}
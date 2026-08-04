<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class UserAddress extends Model
{
    use HasFactory, Auditable; 

    protected $table = 'user_addresses';

    protected $fillable = ['user_id', 'label', 'contact_name', 'address', 'postcode', 'latitude', 'longitude', 'is_default'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
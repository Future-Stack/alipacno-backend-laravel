<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HqAdmin extends Model
{
    use HasFactory;

    protected $table = 'hq_admins';

    protected $fillable = ['user_id', 'name', 'email', 'phone', 'password', 'avatar', 'last_login', 'status'];
    protected $hidden = ['password'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }



}
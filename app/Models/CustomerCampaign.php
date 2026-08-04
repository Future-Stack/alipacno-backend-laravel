<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerCampaign extends Model
{
    use HasFactory;

    protected $table = 'customer_campaigns';

    protected $fillable = ['campaign_id', 'user_id', 'status'];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
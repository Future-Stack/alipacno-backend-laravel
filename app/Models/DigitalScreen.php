<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DigitalScreen extends Model
{
    use HasFactory;

    protected $table = 'digital_screens';

    protected $fillable = ['branch_id', 'screen_name', 'screen_group_id', 'device_uuid', 'resolution', 'location', 'status', 'last_sync'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function screenGroup()
    {
        return $this->belongsTo(ScreenGroup::class);
    }
}
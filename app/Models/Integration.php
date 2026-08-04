<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use HasFactory;

    protected $table = 'integrations';

    protected $fillable = ['branch_id', 'integration_name', 'integration_type', 'api_key', 'secret', 'webhook_url', 'settings', 'status'];

    protected $casts = [
        'settings' => 'array',
    ];

    protected $hidden = ['secret'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
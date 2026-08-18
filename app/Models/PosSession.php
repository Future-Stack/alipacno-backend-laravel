<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosSession extends Model
{
    use HasFactory;

    protected $table = 'pos_sessions';

    protected $fillable = [
        'branch_id',
        'staff_id',
        'opening_balance',
        'cash_sales',
        'card_sales',
        'opening_cash',
        'closing_cash',
        'total_sales',
        'status',
        'opened_at',
        'closed_at',
        'user_id'
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
        
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
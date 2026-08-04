<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosSession extends Model
{
    use HasFactory;

    protected $table = 'pos_sessions';

    protected $fillable = ['branch_id', 'staff_id', 'opening_cash', 'closing_cash', 'total_sales', 'status', 'opened_at', 'closed_at'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
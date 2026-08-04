<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreenLog extends Model
{
    use HasFactory;

    protected $table = 'screen_logs';

    protected $fillable = ['screen_id', 'status', 'remarks'];

    public function screen()
    {
        return $this->belongsTo(DigitalScreen::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreenGroupScreen extends Model
{
    use HasFactory;

    protected $table = 'screen_group_screens';

    protected $fillable = ['screen_group_id', 'screen_id'];

    public function screenGroup()
    {
        return $this->belongsTo(ScreenGroup::class);
    }

    public function screen()
    {
        return $this->belongsTo(DigitalScreen::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreenImpression extends Model
{
    use HasFactory;

    protected $table = 'screen_impressions';

    protected $fillable = ['screen_id', 'content_id', 'total_views', 'play_count', 'play_duration', 'recorded_at'];

    public function screen()
    {
        return $this->belongsTo(DigitalScreen::class);
    }

    public function content()
    {
        return $this->belongsTo(SignageContent::class);
    }
}
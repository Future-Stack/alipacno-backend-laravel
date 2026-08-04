<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreenSchedule extends Model
{
    use HasFactory;

    protected $table = 'screen_schedules';

    protected $fillable = ['screen_id', 'playlist_id', 'start_date', 'end_date', 'start_time', 'end_time', 'repeat_type', 'priority', 'status'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'priority' => 'integer',
    ];

    public function screen()
    {
        return $this->belongsTo(DigitalScreen::class, 'screen_id');
    }

    public function playlist()
    {
        return $this->belongsTo(ScreenPlaylist::class, 'playlist_id');
    }

    /**
     * Scope query to filter active schedules.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to filter by screen.
     */
    public function scopeForScreen($query, $screenId)
    {
        if (empty($screenId)) {
            return $query;
        }

        return $query->where('screen_id', $screenId);
    }

    /**
     * Scope query to filter by playlist.
     */
    public function scopeForPlaylist($query, $playlistId)
    {
        if (empty($playlistId)) {
            return $query;
        }

        return $query->where('playlist_id', $playlistId);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScreenSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'screen_schedules';

    protected $fillable = [
        'screen_id',
        'playlist_id',
        'signage_content_id',
        'schedule_name',
        'title',
        'display_type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'recurrence',
        'repeat_type',
        'branch_ids',
        'screen_group_ids',
        'screen_ids',
        'priority',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'branch_ids' => 'array',
        'screen_group_ids' => 'array',
        'screen_ids' => 'array',
        'priority' => 'integer',
    ];

    protected $appends = ['branches', 'screen_groups', 'screens'];

    public function getBranchesAttribute()
    {
        if (empty($this->branch_ids)) {
            return [];
        }
        return Branch::whereIn('id', $this->branch_ids)->select('id', 'name')->get();
    }

    public function getScreenGroupsAttribute()
    {
        if (empty($this->screen_group_ids)) {
            return [];
        }
        return ScreenGroup::whereIn('id', $this->screen_group_ids)->select('id', 'name')->get();
    }

    public function getScreensAttribute()
    {
        if (empty($this->screen_ids)) {
            return [];
        }
        return DigitalScreen::whereIn('id', $this->screen_ids)->select('id', 'screen_name')->get();
    }

    public function screen()
    {
        return $this->belongsTo(DigitalScreen::class, 'screen_id');
    }

    public function playlist()
    {
        return $this->belongsTo(ScreenPlaylist::class, 'playlist_id');
    }

    public function signageContent()
    {
        return $this->belongsTo(SignageContent::class, 'signage_content_id');
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
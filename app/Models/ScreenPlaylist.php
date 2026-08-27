<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScreenPlaylist extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'screen_playlists';

    protected $fillable = ['title', 'description', 'created_by'];

    public function items()
    {
        return $this->hasMany(ScreenPlaylistItem::class, 'playlist_id')->orderBy('sort_order', 'asc');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query to search playlists by title or description.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignageContent extends Model
{
    use HasFactory;

    protected $table = 'signage_contents';

    protected $fillable = ['title', 'content_type', 'file', 'thumbnail', 'duration', 'status'];

    protected $casts = [
        'duration' => 'integer',
    ];

    public function playlistItems()
    {
        return $this->hasMany(ScreenPlaylistItem::class, 'signage_content_id');
    }

    /**
     * Scope query to filter active content.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to filter by content type.
     */
    public function scopeByType($query, $type)
    {
        if (empty($type)) {
            return $query;
        }

        return $query->where('content_type', $type);
    }

    /**
     * Scope query to search content title.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where('title', 'like', "%{$search}%");
    }
}
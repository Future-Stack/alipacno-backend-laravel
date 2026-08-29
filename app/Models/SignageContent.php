<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SignageContent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'signage_contents';

    protected $fillable = [
        'title',
        'content_name',
        'content_type',
        'campaign_tag',
        'description',
        'file',
        'thumbnail',
        'resolution',
        'file_size',
        'dimensions',
        'aspect_ratio',
        'duration',
        'status',
    ];

    protected $casts = [
        'duration' => 'integer',
    ];

    protected $appends = ['file_url', 'thumbnail_url'];

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file) {
            return null;
        }
        return str_starts_with($this->file, 'http') || str_starts_with($this->file, '/')
            ? $this->file
            : asset('storage/' . $this->file);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail) {
            return $this->content_type === 'image' ? $this->file_url : null;
        }
        return str_starts_with($this->thumbnail, 'http') || str_starts_with($this->thumbnail, '/')
            ? $this->thumbnail
            : asset('storage/' . $this->thumbnail);
    }

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
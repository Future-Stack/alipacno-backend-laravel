<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScreenPlaylistItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'screen_playlist_items';

    protected $fillable = ['playlist_id', 'signage_content_id', 'sort_order'];

    public function playlist()
    {
        return $this->belongsTo(ScreenPlaylist::class);
    }

    public function content()
    {
        return $this->belongsTo(SignageContent::class);
    }
}
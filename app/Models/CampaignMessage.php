<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignMessage extends Model
{
    use HasFactory;

    protected $table = 'campaign_messages';

    protected $fillable = ['campaign_id', 'channel', 'message'];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * Scope query to filter messages by campaign.
     */
    public function scopeForCampaign($query, $campaignId)
    {
        if (empty($campaignId)) {
            return $query;
        }

        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope query to filter messages by channel.
     */
    public function scopeByChannel($query, $channel)
    {
        if (empty($channel)) {
            return $query;
        }

        return $query->where('channel', $channel);
    }

    /**
     * Scope query to search message text or channel.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('channel', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%");
        });
    }
}
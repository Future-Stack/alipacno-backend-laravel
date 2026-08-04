<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignStatistic extends Model
{
    use HasFactory;

    protected $table = 'campaign_statistics';

    protected $fillable = ['campaign_id', 'sent', 'delivered', 'opened', 'clicked', 'converted'];

    protected $casts = [
        'sent' => 'integer',
        'delivered' => 'integer',
        'opened' => 'integer',
        'clicked' => 'integer',
        'converted' => 'integer',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * Scope query to filter statistics by campaign.
     */
    public function scopeForCampaign($query, $campaignId)
    {
        if (empty($campaignId)) {
            return $query;
        }

        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope query to search by campaign name.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->whereHas('campaign', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%");
        });
    }
}
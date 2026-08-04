<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignAutomationFlow extends Model
{
    use HasFactory;

    protected $table = 'campaign_automation_flows';

    protected $fillable = ['campaign_id', 'trigger', 'condition', 'action', 'status'];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * Scope query to filter active automation flows.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to filter flows by campaign.
     */
    public function scopeForCampaign($query, $campaignId)
    {
        if (empty($campaignId)) {
            return $query;
        }

        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope query to search trigger, condition, or action.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('trigger', 'like', "%{$search}%")
                ->orWhere('condition', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%");
        });
    }
}
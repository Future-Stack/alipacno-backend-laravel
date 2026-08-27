<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignAutomationFlow extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'campaign_automation_flows';

    protected $fillable = [
        'campaign_id',
        'campaign_title',
        'gender',
        'postcode',
        'marketing_type',
        'start_date',
        'end_date',
        'period',
        'campaign_description_details',
        'attachment',
        'trigger',
        'condition',
        'action',
        'status',
        'sent_count',
        'delivered_count',
        'failed_count',
        'opened_count',
        'replies_count',
        'created_by',
    ];

    protected $appends = ['attachment_url', 'flow_integration_status'];

    public function getFlowIntegrationStatusAttribute(): string
    {
        return $this->status === 'active' ? 'ACTIVE STATE' : 'INACTIVE';
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment) {
            return null;
        }
        return str_starts_with($this->attachment, 'http') || str_starts_with($this->attachment, '/')
            ? $this->attachment
            : asset('storage/' . $this->attachment);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

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
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignRecipient extends Model
{
    use HasFactory;

    protected $table = 'campaign_recipients';

    protected $fillable = ['campaign_id', 'user_id', 'status', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope query to filter recipients by campaign.
     */
    public function scopeForCampaign($query, $campaignId)
    {
        if (empty($campaignId)) {
            return $query;
        }

        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope query to filter recipients by user.
     */
    public function scopeByUser($query, $userId)
    {
        if (empty($userId)) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    /**
     * Scope query to filter recipients by status.
     */
    public function scopeByStatus($query, $status)
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope query to search recipient user name or email.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->whereHas('user', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }
}
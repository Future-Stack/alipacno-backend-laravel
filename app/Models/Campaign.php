<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $table = 'campaigns';

    protected $fillable = ['name', 'type', 'subject', 'message', 'status', 'created_by'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients()
    {
        return $this->hasMany(CampaignRecipient::class, 'campaign_id');
    }

    public function messages()
    {
        return $this->hasMany(CampaignMessage::class, 'campaign_id');
    }

    public function automationFlows()
    {
        return $this->hasMany(CampaignAutomationFlow::class, 'campaign_id');
    }

    public function statistics()
    {
        return $this->hasOne(CampaignStatistic::class, 'campaign_id');
    }

    /**
     * Scope query to filter campaigns by status.
     */
    public function scopeByStatus($query, $status)
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope query to filter campaigns by type.
     */
    public function scopeByType($query, $type)
    {
        if (empty($type)) {
            return $query;
        }

        return $query->where('type', $type);
    }

    /**
     * Scope query to search campaign name, subject, or message.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%");
        });
    }
}
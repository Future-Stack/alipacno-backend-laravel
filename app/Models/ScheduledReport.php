<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledReport extends Model
{
    use HasFactory;

    protected $table = 'scheduled_reports';

    protected $fillable = ['report_id', 'frequency', 'email_to', 'last_run', 'next_run', 'status'];

    protected $casts = [
        'last_run' => 'datetime',
        'next_run' => 'datetime',
    ];

    public function savedReport()
    {
        return $this->belongsTo(SavedReport::class, 'report_id');
    }

    /**
     * Scope query to filter active scheduled reports.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to filter by frequency.
     */
    public function scopeByFrequency($query, $frequency)
    {
        if (empty($frequency)) {
            return $query;
        }

        return $query->where('frequency', $frequency);
    }

    /**
     * Scope query to search recipient emails.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where('email_to', 'like', "%{$search}%");
    }
}
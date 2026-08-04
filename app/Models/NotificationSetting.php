<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $table = 'notification_settings';

    protected $fillable = ['user_id', 'branch_admin_id', 'order_alert', 'branch_alert', 'low_stock_alert', 'driver_alert', 'marketing_report', 'daily_summary', 'email_notification', 'sms_notification', 'push_notification'];

    protected $casts = [
        'order_alert' => 'boolean',
        'branch_alert' => 'boolean',
        'low_stock_alert' => 'boolean',
        'driver_alert' => 'boolean',
        'marketing_report' => 'boolean',
        'daily_summary' => 'boolean',
        'email_notification' => 'boolean',
        'sms_notification' => 'boolean',
        'push_notification' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branchAdmin()
    {
        return $this->belongsTo(BranchAdmin::class);
    }
}
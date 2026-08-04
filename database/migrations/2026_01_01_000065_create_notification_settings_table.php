<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('branch_admin_id')->nullable();
            $table->boolean('order_alert')->default(true);
            $table->boolean('branch_alert')->default(true);
            $table->boolean('low_stock_alert')->default(true);
            $table->boolean('driver_alert')->default(true);
            $table->boolean('marketing_report')->default(true);
            $table->boolean('daily_summary')->default(true);
            $table->boolean('email_notification')->default(true);
            $table->boolean('sms_notification')->default(true);
            $table->boolean('push_notification')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
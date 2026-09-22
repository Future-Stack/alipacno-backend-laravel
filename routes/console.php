<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

//Artisan::command('inspire', function () {
//    $this->comment(Inspiring::quote());
//})->purpose('Display an inspiring quote');

/**
 * Schedule Weekly Driver Payout Processing
 * Runs every Monday at midnight (00:00) for Week N (1-week payment lag)
 */
Schedule::command('drivers:process-weekly-payouts')->weeklyOn(1, '00:00');

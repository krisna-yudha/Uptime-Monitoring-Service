<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule monitor checks to run every minute, but monitors will check based on their individual interval_seconds
// For 10-second intervals, individual monitors will handle the timing
Schedule::command('monitor:check')->everySecond();

// Schedule data aggregation - runs every minute to aggregate the previous minute's data
Schedule::command('metrics:aggregate --interval=minute')->everyMinute();

// Schedule hourly aggregation - runs at the start of each hour
Schedule::command('metrics:aggregate --interval=hour')->hourly();

// Schedule daily aggregation - runs at 1:00 AM every day
Schedule::command('metrics:aggregate --interval=day')->dailyAt('01:00');

// Schedule cleanup of old raw data - runs daily at 2:00 AM
Schedule::command('metrics:cleanup')->dailyAt('02:00');

// Schedule cleanup of old monitoring logs (runs every 30 days at 3:00 AM)
// Deletes logs older than 30 days to prevent database bloat
Schedule::command('logs:cleanup')->monthlyOn(1, '03:00');

// Schedule queue jobs cleanup - runs every 5 minutes
// Keeps maximum 5000 jobs in queue to prevent bloat
Schedule::command('queue:cleanup --max-jobs=5000')->everyFiveMinutes();
    
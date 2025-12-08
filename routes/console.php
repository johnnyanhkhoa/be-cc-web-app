<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule auto call assignment daily at 6:00 AM MM time
Schedule::command('calls:assign-daily')
    ->dailyAt('06:00')  // ← 06:00 Myanmar time
    ->timezone('Asia/Yangon')  // ← Bỏ comment
    ->appendOutputTo(storage_path('logs/call-assignment.log'));

// Schedule sync phone collections daily at 5:30 AM MM time
Schedule::command('sync:phone-collections')
    ->dailyAt('05:30')  // ← 05:30 Myanmar time
    ->timezone('Asia/Yangon')  // ← Bỏ comment
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/phone-collection-sync.log'));

// Send daily phone collection report at 9:00 AM Myanmar time
Schedule::command('report:send-daily-phone-collection')
    ->dailyAt('02:30')  // 02:30 UTC = 09:00 Myanmar time
    ->timezone('UTC')
    ->appendOutputTo(storage_path('logs/daily-report.log'));

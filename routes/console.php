<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule auto call assignment daily at 6:00 AM MM time
Schedule::command('calls:assign-daily')
    ->dailyAt('23:30')
    // ->timezone('Asia/Ho_Chi_Minh')
    ->appendOutputTo(storage_path('logs/call-assignment.log'));

// Thêm dòng này - Schedule sync phone collections daily at 5:30 AM MM time
Schedule::command('sync:phone-collections')
    ->dailyAt('23:00')
    // ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/phone-collection-sync.log'));

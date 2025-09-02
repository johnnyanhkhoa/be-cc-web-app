<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule auto call assignment daily at 7:00 AM
Schedule::command('calls:assign-daily')
    ->dailyAt('07:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->appendOutputTo(storage_path('logs/call-assignment.log'));

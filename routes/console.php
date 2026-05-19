<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Email log cleanup - runs daily at 2:00 AM, keeps logs for 180 days
Schedule::command('email:cleanup-logs --days=180')->dailyAt('02:00');

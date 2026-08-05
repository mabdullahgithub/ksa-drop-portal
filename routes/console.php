<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Email log cleanup - runs daily at 2:00 AM, keeps logs for 180 days
Schedule::command('email:cleanup-logs --days=180')->dailyAt('02:00');

// Sync shipment tracking every 10 minutes. iMile has no push/webhook API
// (pull-only) but client/track/list batches up to 100 orders per call, so
// polling this often is cheap; J&T shipments are also covered live by
// webhooks/jnt-express/* and use this mainly as a fallback.
Schedule::command('shipments:sync-tracking --limit=200')
    ->everyTenMinutes()
    ->withoutOverlapping();

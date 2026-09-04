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

// Drains the Shopify webhook queue. The webhook endpoint pins its job to the
// `database` connection so it can answer Shopify immediately; this is what then
// does the work. --stop-when-empty keeps the run short, and --max-time caps it
// under the minute so the next tick always starts clean.
//
// Named explicitly so it drains only that connection: the app's default is
// `sync`, and mail must keep sending inline rather than waiting on a worker.
//
// The mutex expiry is explicit and short. withoutOverlapping() defaults to 24
// hours, and a worker killed outside PHP's reach — an OOM kill on shared
// hosting, a process reaper — never releases its lock. The queue would then be
// skipped every tick for a day while the endpoint kept answering 200, so
// Shopify would never retry and orders would simply stop appearing. Two minutes
// covers the 55-second run with room to spare and bounds that failure to one
// missed tick.
Schedule::command('queue:work database --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2);

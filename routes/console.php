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

// Replay Shopify webhooks that failed to sync into orders. Each parked failure
// carries its own backoff (1m → 24h over 8 attempts), so sweeping every five
// minutes just means "check what's due" — it does not retry anything faster
// than its own schedule allows.
Schedule::command('shopify:retry-failed-syncs --limit=100')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Recover orders Shopify never managed to deliver. A failed webhook delivery
// gets 8 attempts over 4 hours from Shopify and is then dropped — nothing ever
// reaches our endpoint, so the dead-letter queue never sees it and the order
// exists only in Shopify. Hourly with a 6-hour lookback so a run that is missed
// or fails is covered by the next several.
Schedule::command('shopify:reconcile-orders --hours=6')
    ->hourly()
    ->withoutOverlapping();

// Shopify removes a webhook subscription after repeated delivery failures in a
// 24-hour period and never notifies us — order sync then stops dead, silently
// and with no failures to show for it. Check twice a day and re-register.
Schedule::command('shopify:verify-webhooks')
    ->twiceDaily(3, 15)
    ->withoutOverlapping();

// Settled sync failures still hold the webhook payload that produced them, so
// the table grows fastest exactly when things are going wrong. Resolved rows are
// dead weight within a fortnight; abandoned ones are kept a quarter, since they
// are the record of orders that never arrived.
Schedule::command('shopify:cleanup-sync-failures')->dailyAt('03:30');

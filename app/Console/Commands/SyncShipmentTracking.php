<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\Shipping\Contracts\CourierDriver;
use App\Services\Shipping\CourierManager;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\DTOs\TrackingResult;
use App\Services\Shipping\Drivers\ImileDriver;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncShipmentTracking extends Command
{
    protected $signature = 'shipments:sync-tracking {--limit=50}';

    protected $description = 'Sync tracking info for all active shipments from courier APIs';

    public function handle(CourierManager $courierManager): int
    {
        $limit = (int) $this->option('limit');

        $shipments = Shipment::trackable()
            ->whereNotNull('tracking_number')
            ->orderBy('updated_at', 'asc')
            ->limit($limit)
            ->get();

        if ($shipments->isEmpty()) {
            $this->info('No active shipments to sync.');
            return self::SUCCESS;
        }

        $this->info("Syncing tracking for {$shipments->count()} shipment(s)...");

        $updated = 0;
        $failed = 0;

        foreach ($shipments->groupBy('courier') as $courier => $group) {
            $driver = $courierManager->driver($courier);

            // iMile supports batched tracking (up to 100 orders per
            // client/track/list call) — fetch a whole courier's active
            // shipments in a couple of API calls instead of one per shipment.
            [$batchUpdated, $batchFailed] = $driver instanceof ImileDriver
                ? $this->syncBatched($driver, $group)
                : $this->syncOneByOne($driver, $group);

            $updated += $batchUpdated;
            $failed += $batchFailed;
        }

        $this->info("Done. Updated: {$updated}, Failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Shipment>  $shipments
     * @return array{0: int, 1: int} [updated, failed]
     */
    private function syncBatched(ImileDriver $driver, Collection $shipments): array
    {
        $updated = 0;
        $failed = 0;

        $byTrackingNumber = $shipments->keyBy('tracking_number');

        foreach ($byTrackingNumber->keys()->chunk(100) as $chunk) {
            $results = $driver->trackShipments($chunk->all());

            foreach ($results as $trackingNumber => $result) {
                $shipment = $byTrackingNumber->get($trackingNumber);

                if (! $shipment) {
                    continue;
                }

                if (! $result->success) {
                    $failed++;
                    $this->warn("  Failed: {$trackingNumber} - {$result->errorMessage}");
                    continue;
                }

                $this->applyTrackingResult($shipment, $result);
                $updated++;
            }
        }

        return [$updated, $failed];
    }

    /**
     * @param  Collection<int, Shipment>  $shipments
     * @return array{0: int, 1: int} [updated, failed]
     */
    private function syncOneByOne(CourierDriver $driver, Collection $shipments): array
    {
        $updated = 0;
        $failed = 0;

        foreach ($shipments as $shipment) {
            try {
                $result = $driver->trackShipment($shipment->tracking_number);

                if (! $result->success) {
                    $failed++;
                    $this->warn("  Failed: {$shipment->tracking_number} - {$result->errorMessage}");
                    continue;
                }

                $this->applyTrackingResult($shipment, $result);
                $updated++;

                // Rate limiting: pause between requests
                usleep(200000); // 200ms
            } catch (\Exception $e) {
                $failed++;
                $this->error("  Error: {$shipment->tracking_number} - {$e->getMessage()}");
            }
        }

        return [$updated, $failed];
    }

    private function applyTrackingResult(Shipment $shipment, TrackingResult $result): void
    {
        // Mirror what WebhookController::handleJntExpress() stores for J&T's
        // push path, so courier_status/courier_status_description (shown in
        // Shipment Analytics and the CSV export) are populated the same way
        // regardless of courier or whether the update arrived via webhook or
        // polling — previously only the J&T webhook path set these, leaving
        // them permanently blank for iMile (and for J&T shipments only ever
        // synced via this polling fallback).
        $latest = $this->latestEvent($result->events);

        $attributes = [
            'status' => $result->currentStatus->value,
            'tracking_history' => array_map(fn ($e) => $e->toArray(), $result->events),
        ];

        if ($latest !== null) {
            $attributes['courier_status'] = $latest->rawStatus;
            $attributes['courier_status_description'] = $latest->description;
        }

        $shipment->update($attributes);

        if ($result->currentStatus === ShipmentStatus::DELIVERED) {
            $shipment->markDelivered();
        }
    }

    /**
     * The most recent scan (by timestamp) drives courier_status/description —
     * same rule WebhookController::latestEvent() uses for J&T's push path.
     *
     * @param  TrackingEvent[]  $events
     */
    private function latestEvent(array $events): ?TrackingEvent
    {
        if ($events === []) {
            return null;
        }

        $latest = $events[0];

        foreach ($events as $event) {
            if (strcmp($event->timestamp, $latest->timestamp) >= 0) {
                $latest = $event;
            }
        }

        return $latest;
    }
}

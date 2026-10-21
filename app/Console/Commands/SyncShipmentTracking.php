<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\Shipping\Contracts\CourierDriver;
use App\Services\Shipping\CourierManager;
use App\Services\Shipping\Drivers\ImileDriver;
use App\Services\Shipping\Drivers\KsaDropExpressDriver;
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
            // KSA Express has no courier to ask — its riders record status as
            // it happens (ShipmentEventRecorder). Polling it would only
            // rewrite tracking_history from a copy read moments earlier, and
            // could drop an update a rider made in between.
            ->where('courier', '!=', KsaDropExpressDriver::KEY)
            // Least recently asked first (never asked before all), so every
            // active shipment gets its turn even when there are more than
            // --limit of them.
            ->orderByRaw('tracking_synced_at IS NOT NULL')
            ->orderBy('tracking_synced_at')
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

        // Stamped whatever the outcome, so a shipment the courier keeps
        // failing on goes to the back of the queue instead of blocking it.
        // Through the base query, so updated_at keeps meaning "changed".
        Shipment::whereIn('id', $shipments->pluck('id'))
            ->toBase()
            ->update(['tracking_synced_at' => now()]);

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

        foreach ($driver->trackShipments($byTrackingNumber->keys()->all()) as $trackingNumber => $result) {
            $shipment = $byTrackingNumber->get($trackingNumber);

            if (! $shipment) {
                continue;
            }

            if (! $result->success) {
                $failed++;
                $this->warn("  Failed: {$trackingNumber} - {$result->errorMessage}");
                continue;
            }

            $shipment->applyPulledTracking($result);
            $updated++;
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

                $shipment->applyPulledTracking($result);
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
}

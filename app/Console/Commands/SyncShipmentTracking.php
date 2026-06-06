<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\Shipping\CourierManager;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Console\Command;

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

        foreach ($shipments as $shipment) {
            try {
                $driver = $courierManager->driver($shipment->courier);
                $result = $driver->trackShipment($shipment->tracking_number);

                if (! $result->success) {
                    $failed++;
                    $this->warn("  Failed: {$shipment->tracking_number} - {$result->errorMessage}");
                    continue;
                }

                $shipment->update([
                    'status' => $result->currentStatus->value,
                    'tracking_history' => array_map(fn ($e) => $e->toArray(), $result->events),
                ]);

                if ($result->currentStatus === ShipmentStatus::DELIVERED) {
                    $shipment->markDelivered();
                }

                $updated++;

                // Rate limiting: pause between requests
                usleep(200000); // 200ms
            } catch (\Exception $e) {
                $failed++;
                $this->error("  Error: {$shipment->tracking_number} - {$e->getMessage()}");
            }
        }

        $this->info("Done. Updated: {$updated}, Failed: {$failed}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Log;

/**
 * Keeps stock in step with where a parcel physically is.
 *
 * Hooking the model rather than each caller is deliberate: courier status is
 * written from five places (the J&T, iMile and LogesTechs webhooks, the
 * SyncShipmentTracking polling command and the on-demand refresh in
 * ShipmentController) plus the mark*() helpers on the model. All of them end
 * in a save on the shipment, so this is the one point every path crosses.
 */
class ShipmentObserver
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function created(Shipment $shipment): void
    {
        // A shipment can be created already dispatched (backfill, or a courier
        // that books and picks up in one call).
        $this->sync($shipment);
    }

    public function updated(Shipment $shipment): void
    {
        if (! $shipment->wasChanged('status')) {
            return;
        }

        $this->sync($shipment);
    }

    private function sync(Shipment $shipment): void
    {
        $status = $shipment->status_enum;

        try {
            if ($status->deductsStock()) {
                $this->inventory->deductForShipment($shipment);

                return;
            }

            if ($status->restocksStock()) {
                $this->inventory->restockForShipment($shipment);
            }
        } catch (\Throwable $e) {
            // Stock accounting must never break tracking ingestion. A failure
            // leaves the shipment's fast-path timestamps unset, so the next
            // status push retries — safely, because any movement that did land
            // before the failure is fenced off by its unique dedupe key.
            //
            // Note this cannot swallow a rollback: when the caller wrapped the
            // status write in its own transaction (the webhook handlers do),
            // the movements are part of it and unwind with it.
            Log::error('Inventory sync failed for shipment ' . $shipment->id . ': ' . $e->getMessage(), [
                'shipment_id' => $shipment->id,
                'status'      => $status->value,
                'exception'   => get_class($e),
            ]);
        }
    }
}

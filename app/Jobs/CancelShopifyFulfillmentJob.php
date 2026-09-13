<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Services\ShopifyFulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Undo a Shopify fulfillment after the parcel came back to us.
 *
 * Queued off Shipment::markReturned(), which runs from courier webhooks and the
 * tracking sweep — paths that must record the return in our own database
 * whatever Shopify does.
 *
 * Left undone, the merchant's order stays Fulfilled with a live tracking number
 * their customer can still follow, and every report they run from that point is
 * wrong.
 */
class CancelShopifyFulfillmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(private Shipment $shipment) {}

    public function handle(ShopifyFulfillmentService $fulfillment): void
    {
        $fulfillment->cancelFulfillment($this->shipment);
    }
}

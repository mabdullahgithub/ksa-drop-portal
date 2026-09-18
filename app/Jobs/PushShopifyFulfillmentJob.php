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
 * Report a booked waybill to Shopify as a fulfillment.
 *
 * Queued, and never inline, for one reason that matters more than latency: by
 * the time this runs the courier has already accepted the parcel and taken our
 * money. Shopify being down at that moment must not turn a successful booking
 * into a failed request, or leave the operator pressing the button again and
 * creating a second waybill.
 *
 * Retries cover the outage. Creating a fulfillment is guarded on the shipment's
 * stored fulfillment id, so a retry after a response we never read reports the
 * existing one instead of sending the customer a second shipping email.
 */
class PushShopifyFulfillmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** Longer than the order jobs: an Admin API outage outlasts a bad minute. */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(private Shipment $shipment) {}

    public function handle(ShopifyFulfillmentService $fulfillment): void
    {
        $fulfillment->fulfillFromShipment($this->shipment);
    }
}

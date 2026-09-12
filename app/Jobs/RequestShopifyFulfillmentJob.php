<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\ShopifyFulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Ask Shopify to send one order to us for fulfillment.
 *
 * Queued rather than inline because every caller is on a path that must not
 * wait for — or fail on — the Admin API: a webhook that owes Shopify a 200
 * inside five seconds, a client pressing Approve in the portal, a merchant
 * pressing a button in the embedded app.
 *
 * Pinned to the `database` connection for the same reason
 * ProcessShopifyWebhookJob is: the default queue connection is `sync` in
 * production, and under `sync` "queued" work runs inline and takes the caller's
 * latency with it.
 *
 * Retries are for transport failures. A refusal on our side — an unpaid order,
 * a store with no fulfillment service — is a settled answer, not a failure, and
 * the service reports it as an outcome rather than by throwing, so the job ends
 * cleanly and does not come back.
 *
 * Several entry points can queue this for the same order — imported, then
 * approved, then pressed by the merchant. De-duplication is deliberately left
 * to requestFulfillment(), which stops on the stored fulfillment order id: it
 * is the only check that also covers a request submitted from Shopify admin,
 * where no job of ours was ever queued.
 */
class RequestShopifyFulfillmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private Order $order) {}

    public function handle(ShopifyFulfillmentService $fulfillment): void
    {
        $fulfillment->requestFulfillment($this->order);
    }
}

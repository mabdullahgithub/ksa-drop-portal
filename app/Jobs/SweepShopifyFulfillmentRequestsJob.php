<?php

namespace App\Jobs;

use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Services\ShopifyFulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Accept every fulfillment request Shopify is holding for one store.
 *
 * Driven by the /fulfillment_order_notification callback, and deliberately not
 * by the notification's contents: it asks Shopify what is actually outstanding
 * at our location. That makes it a recovery path rather than a duplicate of the
 * webhook — a request whose `fulfillment_request_submitted` delivery failed all
 * of Shopify's retries is invisible to us until something goes and looks, and
 * the merchant sees only an order that has sat "requested" for days.
 *
 * Accepting an already-accepted fulfillment order is refused by Shopify with a
 * user error, which is logged and skipped, so overlapping with the webhook path
 * costs nothing worse than a log line.
 */
class SweepShopifyFulfillmentRequestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private string $shopDomain) {}

    public function handle(ShopifyFulfillmentService $fulfillment): void
    {
        $connection = ClientShopifyConnection::where('shop_domain', $this->shopDomain)
            ->where('status', 'active')
            ->first();

        if (! $connection || ! $connection->hasFulfillmentService()) {
            return;
        }

        $outstanding = $fulfillment->assignedFulfillmentOrders($connection, 'FULFILLMENT_REQUESTED');

        foreach ($outstanding as $fulfillmentOrder) {
            if (! $fulfillment->acceptRequest($connection, $fulfillmentOrder['id'])) {
                continue;
            }

            $this->recordAgainstOrder($fulfillmentOrder['id'], $fulfillmentOrder['orderId']);
        }

        if ($outstanding !== []) {
            Log::channel('shopify')->info('Swept outstanding fulfillment requests', [
                'shop'  => $this->shopDomain,
                'count' => count($outstanding),
            ]);
        }
    }

    /**
     * Note the accepted fulfillment order on our copy of the order, when we
     * hold one. A request raised in Shopify admin can arrive before the order
     * webhook that creates our copy; there is nothing to write to then, and
     * nothing is lost by it — the id only stops us submitting a request of our
     * own, and none went out here.
     */
    private function recordAgainstOrder(string $fulfillmentOrderId, ?string $orderGid): void
    {
        if (! $orderGid) {
            return;
        }

        $shopifyOrderId = str_contains($orderGid, '/')
            ? substr($orderGid, strrpos($orderGid, '/') + 1)
            : $orderGid;

        Order::withoutGlobalScope('shopify_visible')
            ->where('shopify_order_id', $shopifyOrderId)
            ->where('shopify_shop_domain', $this->shopDomain)
            ->update([
                'shopify_fulfillment_order_id' => $fulfillmentOrderId,
                'shopify_fulfillment_status'   => 'accepted',
            ]);
    }
}

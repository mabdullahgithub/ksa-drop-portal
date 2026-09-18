<?php

namespace App\Http\Controllers\Embedded;

use App\Http\Controllers\Controller;
use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Services\EmbeddedPayloadService;
use App\Services\ShopifyFulfillmentService;
use Illuminate\Http\Request;

class EmbeddedDashboardController extends Controller
{
    public function __construct(
        private EmbeddedPayloadService $payload,
        private ShopifyFulfillmentService $fulfillment,
    ) {}

    /**
     * Merchant-readable outcomes for a fulfillment request.
     *
     * The refusals matter more than the success here. A button that reports
     * nothing when it declines to act reads as broken, and the two refusals a
     * merchant will actually meet — an unpaid order, and a product that was
     * never routed to us — both have a specific thing they can go and do about
     * it.
     */
    private const OUTCOMES = [
        ShopifyFulfillmentService::REQUEST_SENT =>
            'Sent to KSA Drop for fulfillment.',
        ShopifyFulfillmentService::REQUEST_ALREADY_SENT =>
            'This order has already been sent to KSA Drop.',
        ShopifyFulfillmentService::REQUEST_AWAITING_PAYMENT =>
            'This order is still awaiting payment, so it has not been sent for fulfillment.',
        ShopifyFulfillmentService::REQUEST_NO_FULFILLMENT_ORDER =>
            'None of the products on this order are stocked at your KSA Drop location. Re-import the catalogue CSV to route them to us.',
        ShopifyFulfillmentService::REQUEST_UNAVAILABLE =>
            'Fulfillment requests are not available for this store yet.',
        ShopifyFulfillmentService::REQUEST_FAILED =>
            'Shopify could not be reached. Please try again.',
    ];

    /**
     * Sync stats + recent orders for the embedded dashboard. The connection is
     * resolved by the shopify.session middleware from the session token.
     *
     * The payload itself is built by EmbeddedPayloadService, shared with the
     * Blade shell — which inlines the same JSON on the initial load so the
     * first paint doesn't have to wait for this request.
     */
    public function index(Request $request)
    {
        /** @var ClientShopifyConnection $connection */
        $connection = $request->attributes->get('shopify_connection');

        return response()->json($this->payload->dashboard($connection));
    }

    /**
     * Ask Shopify to send one order to us — the merchant-initiated half of App
     * Store requirement 5.5.1.
     *
     * Run inline rather than queued, unlike every other caller of
     * requestFulfillment(): a merchant who has just pressed a button is owed
     * the actual answer, and "awaiting payment" or "not routed to us" are only
     * useful if they arrive while they are still looking at the order.
     */
    public function requestFulfillment(Request $request, int $order)
    {
        /** @var ClientShopifyConnection $connection */
        $connection = $request->attributes->get('shopify_connection');

        // Scoped to this store, not just this client: a client who has
        // connected a different store since must not be able to move orders
        // belonging to the old one through the new one's token.
        $order = Order::withoutGlobalScope('shopify_visible')
            ->where('client_id', $connection->client_id)
            ->where('shopify_shop_domain', $connection->shop_domain)
            ->find($order);

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $outcome = $this->fulfillment->requestFulfillment($order);

        return response()->json([
            'outcome'   => $outcome,
            'requested' => $outcome === ShopifyFulfillmentService::REQUEST_SENT
                || $outcome === ShopifyFulfillmentService::REQUEST_ALREADY_SENT,
            'message'   => self::OUTCOMES[$outcome] ?? 'Fulfillment request could not be completed.',
        ], $outcome === ShopifyFulfillmentService::REQUEST_FAILED ? 502 : 200);
    }
}

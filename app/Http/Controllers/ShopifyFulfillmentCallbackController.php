<?php

namespace App\Http\Controllers;

use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The three endpoints Shopify calls on a registered fulfillment service.
 *
 * Their prefix is handed to Shopify once, at fulfillmentServiceCreate
 * (ShopifyFulfillmentService::callbackUrl), and Shopify appends the paths
 * itself — so all three must answer from the moment a service is registered,
 * whether or not we make use of them.
 *
 * Two of them we deliberately leave empty, and that is a design decision rather
 * than an omission:
 *
 *   /fetch_stock              the service is registered with
 *                             inventoryManagement: false — the portal is the
 *                             stock system of record and merchants keep
 *                             managing their own Shopify inventory.
 *
 *   /fetch_tracking_numbers   we push tracking with fulfillmentCreate the
 *                             moment the courier waybill exists, so there is
 *                             never anything here for Shopify to come and
 *                             collect.
 *
 * The real work arrives on the `fulfillment_orders/*` webhook topics, which
 * come through the ordinary webhook pipeline and so inherit its HMAC check,
 * queueing and dead-letter retries. /fulfillment_order_notification is kept as
 * the recovery path for a notification those never delivered — the same role
 * ReconcileShopifyOrders plays for orders.
 */
class ShopifyFulfillmentCallbackController extends Controller
{
    public function __construct(private ShopifyService $shopify) {}

    /**
     * FULFILLMENT_REQUEST / CANCELLATION_REQUEST notification.
     *
     * Answers 200 unconditionally once the HMAC passes: Shopify treats a
     * non-200 as a failed delivery and retries, and there is nothing a retry
     * could fix that sweeping our own assigned fulfillment orders will not.
     */
    public function notification(Request $request)
    {
        if (! $this->shopify->verifyWebhookHmac($request->getContent(), (string) $request->header('X-Shopify-Hmac-Sha256', ''))) {
            Log::channel('shopify')->warning('Fulfillment notification rejected — HMAC mismatch', [
                'shop' => $request->header('X-Shopify-Shop-Domain'),
            ]);

            return response('Unauthorized', 401);
        }

        Log::channel('shopify')->info('Fulfillment order notification received', [
            'shop' => $request->header('X-Shopify-Shop-Domain'),
            'kind' => $request->input('kind'),
        ]);

        return response('OK', 200);
    }

    /**
     * Inventory levels. Empty — see the class comment.
     */
    public function fetchStock()
    {
        return response()->json([]);
    }

    /**
     * Tracking numbers for fulfillments awaiting them. Empty — see the class
     * comment.
     */
    public function fetchTrackingNumbers()
    {
        return response()->json(['tracking_numbers' => (object) []]);
    }
}

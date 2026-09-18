<?php

namespace App\Http\Controllers;

use App\Jobs\SweepShopifyFulfillmentRequestsJob;
use App\Models\ClientShopifyConnection;
use App\Models\Product;
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
 * /fetch_stock reports the portal's catalogue stock by SKU, and Shopify writes
 * it onto the KSADrop location. It is only called for a service switched to
 * inventoryManagement (shopify:fulfillment-stock-sync), and it exists because
 * the product CSV cannot carry stock once a store has more than one location —
 * Shopify drops the quantity column and every import landed at 0.
 *
 * /fetch_tracking_numbers stays empty on purpose: tracking is pushed with
 * fulfillmentCreate the moment the courier waybill exists, so there is never
 * anything here for Shopify to come and collect.
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

        $shop = (string) $request->header('X-Shopify-Shop-Domain', '');
        $kind = (string) $request->input('kind');

        Log::channel('shopify')->info('Fulfillment order notification received', [
            'shop' => $shop,
            'kind' => $kind,
        ]);

        // Queued, and driven by what Shopify says is outstanding rather than by
        // this body: the point of the sweep is to catch the requests whose own
        // webhook never arrived, and those are not in the payload.
        if ($shop !== '' && $kind === 'FULFILLMENT_REQUEST') {
            SweepShopifyFulfillmentRequestsJob::dispatch($shop)->onConnection('database');
        }

        return response('OK', 200);
    }

    /**
     * On-hand stock by SKU, for Shopify to set on the KSADrop location.
     *
     * Shopify asks for a single SKU when a product is first set up and for
     * everything once an hour, and expects {"SKU": on_hand, ...} back. SKUs
     * are matched case-sensitively on Shopify's side, so they are returned
     * exactly as the catalogue holds them.
     *
     * Answered only for a store that is connected and holds a KSADrop service.
     * The figures are the same shared catalogue every dropshipper imports from,
     * so there is nothing store-specific to leak — but an unknown caller still
     * gets nothing, rather than a free read of the whole warehouse.
     *
     * Shopify's documentation does not say whether this request is signed. If
     * an hmac parameter arrives it must verify; if none does, the call is
     * served and logged as unsigned, so the first real request settles the
     * question instead of a guess locking Shopify out.
     */
    public function fetchStock(Request $request)
    {
        $shop = (string) ($request->query('shop') ?: $request->header('X-Shopify-Shop-Domain', ''));
        $sku  = $request->query('sku');

        $signed = $request->query('hmac') !== null;

        if ($signed && ! $this->shopify->verifyOauthHmac($request->query(), $request->server('QUERY_STRING'))) {
            Log::channel('shopify')->warning('fetch_stock rejected — HMAC mismatch', ['shop' => $shop]);

            return response('Unauthorized', 401);
        }

        $connection = $shop !== ''
            ? ClientShopifyConnection::where('shop_domain', $shop)->where('status', 'active')->first()
            : null;

        if (! $connection || ! $connection->hasFulfillmentService()) {
            Log::channel('shopify')->info('fetch_stock ignored — not a connected KSADrop store', [
                'shop'   => $shop,
                'signed' => $signed,
            ]);

            return response()->json((object) []);
        }

        $stock = Product::query()
            ->whereNotNull('variant_sku')
            ->where('variant_sku', '!=', '')
            ->when(is_string($sku) && $sku !== '', fn ($q) => $q->where('variant_sku', $sku))
            ->pluck('variant_inventory_qty', 'variant_sku')
            // Shopify wants whole on-hand units; a negative or missing figure
            // in the catalogue is reported as none rather than passed through.
            ->map(fn ($qty) => max(0, (int) $qty))
            ->all();

        // Every call is logged, not just failures. Which requests Shopify makes,
        // and when, is exactly what its documentation leaves out — this is how
        // we find out whether an import triggers a lookup at all.
        Log::channel('shopify')->info('fetch_stock served', [
            'shop'      => $shop,
            'sku'       => $sku,
            'skus_sent' => count($stock),
            'signed'    => $signed,
            'params'    => array_keys($request->query()),
        ]);

        return response()->json((object) $stock);
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

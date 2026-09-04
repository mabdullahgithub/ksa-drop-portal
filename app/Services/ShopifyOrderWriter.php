<?php

namespace App\Services;

use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Models\ShopifySyncFailure;
use Illuminate\Support\Facades\DB;

/**
 * The single place a Shopify order is written into our tables.
 *
 * Two paths reach it: the live webhook (ProcessShopifyWebhookJob) and the
 * reconciliation poll that pulls orders Shopify never managed to deliver. They
 * arrive in different shapes — REST-ish webhook bodies vs GraphQL nodes — but
 * both hand this the already-mapped columns, so the rules that matter (review
 * status, locally-owned fulfillment, item replacement, atomicity) are written
 * once and cannot drift apart.
 */
class ShopifyOrderWriter
{
    /**
     * Fulfillment states our own courier flow owns. Once a shipment reaches one
     * of these, Shipment::markDelivered() / markReturned() has written it onto
     * the order, and Shopify's view of the order is behind ours.
     */
    private const LOCALLY_OWNED_FULFILLMENT = ['fulfilled', 'cancelled'];

    public function __construct(private ShopifyService $shopify) {}

    /**
     * Create or update one order and its line items.
     *
     * @param  array  $data       mapped order columns (mapWebhookOrder / mapGraphqlOrder)
     * @param  array  $lineItems  mapped order_items rows (mapLineItems)
     */
    public function write(array $data, array $lineItems, ClientShopifyConnection $connection): Order
    {
        $existing = Order::withoutGlobalScope('shopify_visible')
            ->where('shopify_order_id', $data['shopify_order_id'])
            ->first();

        // Decide visibility:
        //  - existing order keeps its review decision (never re-queue an approved/dismissed one)
        //  - new order is checked against the merchant's sync filters, then sync mode
        if ($existing) {
            $data['shopify_sync_status'] = $existing->shopify_sync_status;
            $data = $this->preserveLocalFulfillment($data, $existing);

            // Tags are the portal's workflow state once an order is in the list:
            // an operator moves it off Pending as they work it. The mapper always
            // produces the starting set, so writing that through on every
            // orders/updated would drag a Confirmed order back to Pending — and
            // before Pending was added at all, it silently emptied the tags of
            // every Shopify order on each update.
            unset($data['tags']);
        } else {
            $data['shopify_sync_status'] = $this->shopify->evaluateSyncFilters($data, $connection);
        }

        // One transaction around the order and its items. The item replacement
        // below is a delete followed by an insert, and a failure between the two
        // would otherwise leave the order in the portal with no line items at
        // all — then park, so it stays that way until a replay happens to work.
        return DB::transaction(function () use ($data, $lineItems, $connection) {
            $order = Order::withoutGlobalScope('shopify_visible')->updateOrCreate(
                ['shopify_order_id' => $data['shopify_order_id']],
                $data
            );

            // Replace line items wholesale rather than upserting keyed on SKU:
            // an order can have two line items sharing a SKU (or both with no
            // SKU at all, which is common), and matching on lineitem_sku alone
            // collapses them into one row, silently dropping the other. Nothing
            // downstream depends on a Shopify-sourced item keeping a stable row
            // id across syncs, so delete-and-reinsert is both correct and simpler
            // — and it is what makes a re-sync of the same order a no-op rather
            // than a second copy of every item.
            $order->items()->delete();
            $order->items()->createMany($lineItems);

            // The order is in the portal, so anything parked for it is moot —
            // whether this run was a replay, a later webhook that got through on
            // its own, or the reconciliation poll picking it up. Inside the
            // transaction so a rolled-back write never leaves a failure resolved.
            ShopifySyncFailure::resolveFor($connection->shop_domain, $data['shopify_order_id']);

            return $order;
        });
    }

    /**
     * Which of these Shopify order ids we already hold, in any state —
     * including the hidden ones (pending_review, dismissed, skipped_filtered).
     * Reconciliation asks before importing, so it never resurrects an order the
     * merchant dismissed or the sync filters deliberately skipped.
     *
     * Answered for a whole page at once. Asked per order it was one indexed
     * SELECT per order fetched, and since the overwhelming majority of orders a
     * reconciliation sweep sees are ones we already have, that was almost
     * entirely wasted round trips.
     *
     * @param  array<int,string>  $shopifyOrderIds
     * @return array<string,true>  keyed by id, for O(1) lookup
     */
    public function existing(array $shopifyOrderIds): array
    {
        if ($shopifyOrderIds === []) {
            return [];
        }

        return Order::withoutGlobalScope('shopify_visible')
            ->whereIn('shopify_order_id', $shopifyOrderIds)
            ->pluck('shopify_order_id')
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    /**
     * Keep the fulfillment state our courier flow has already reached.
     *
     * Shopify keeps reporting an order as unfulfilled — it was never fulfilled
     * *there*, we ship it — so every routine update carries a state older than
     * ours, and writing it verbatim flips a delivered order back to unfulfilled
     * in the merchant's list. Reconciliation makes this sharper still: it can
     * pull an order days after we delivered it.
     *
     * Only regressions are blocked. Shopify moving the order forward — the
     * merchant fulfilling or cancelling on their own side — still lands.
     */
    private function preserveLocalFulfillment(array $data, Order $existing): array
    {
        $localOwns    = in_array($existing->fulfillment_status, self::LOCALLY_OWNED_FULFILLMENT, true);
        $incomingOwns = in_array($data['fulfillment_status'] ?? null, self::LOCALLY_OWNED_FULFILLMENT, true);

        if ($localOwns && ! $incomingOwns) {
            $data['fulfillment_status'] = $existing->fulfillment_status;
        }

        // A cancellation raised on our side (a returned shipment) has no
        // counterpart in the payload, whose cancelled_at is null — writing that
        // through would erase when it happened while leaving it cancelled.
        if ($existing->cancelled_at && empty($data['cancelled_at'])) {
            $data['cancelled_at'] = $existing->cancelled_at;
        }

        return $data;
    }
}

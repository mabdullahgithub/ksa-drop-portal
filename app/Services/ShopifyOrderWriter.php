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

            // An order keeps the number it was first given. Shopify never
            // renumbers an order, so the only way the mapped number can differ
            // is that orderNumberFor() continued the client's sequence on import.
            $data['order_number'] = $existing->order_number;
        } else {
            $data['shopify_sync_status'] = $this->shopify->evaluateSyncFilters($data, $connection);
            $data['order_number'] = $this->orderNumberFor($data, $connection);
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
     * The portal number for a new order, continuing the client's sequence
     * across every store it has connected.
     *
     * The number is the client's prefix plus Shopify's order number, shifted by
     * an offset that is fixed per store:
     *
     *  - a client's first store has offset 0, so #1001 stays TST1001;
     *  - a store the client already has orders from keeps the offset those
     *    orders were given, so a reconnected store carries on where it was;
     *  - a new store for a client that already has Shopify orders from another
     *    store starts right after the client's highest number. Every store
     *    numbers from #1001, and before this the second store's #1001 hit the
     *    unique index as TST1001 again and every webhook for it failed — the
     *    app-review account, which moves to a fresh store each review, first.
     *
     * Should the shifted number still be taken (an old order reached late by
     * reconciliation, or a non-numeric Shopify name), the order takes the next
     * free number after the client's highest instead. The unique index covers
     * trashed and hidden rows too, so every lookup here ignores global scopes.
     */
    private function orderNumberFor(array $data, ClientShopifyConnection $connection): string
    {
        $prefix = $this->shopify->orderNumberPrefix($connection->client);
        $number = $data['shopify_order_number'] ?? null;

        $candidate = $number === null
            ? $data['order_number']
            : $prefix . ($number + $this->storeOffset($prefix, $number, $data));

        if (! $this->taken($candidate)) {
            return $candidate;
        }

        $next = ($this->highestShopifyNumber($prefix, $data['client_id']) ?? 0) + 1;

        while ($this->taken($prefix . $next)) {
            $next++;
        }

        return $prefix . $next;
    }

    /**
     * How far this store's Shopify numbers are shifted into the client's sequence.
     */
    private function storeOffset(string $prefix, int $number, array $data): int
    {
        // The store's first order was the one that fixed its offset; later ones
        // may have fallen back to the next free number and do not reflect it.
        $first = Order::withoutGlobalScopes()
            ->where('client_id', $data['client_id'])
            ->where('shopify_shop_domain', $data['shopify_shop_domain'])
            ->whereNotNull('shopify_order_number')
            ->orderBy('id')
            ->first(['order_number', 'shopify_order_number']);

        if ($first && ($sequence = $this->sequenceOf($prefix, $first->order_number)) !== null) {
            return $sequence - $first->shopify_order_number;
        }

        $highest = $this->highestShopifyNumber($prefix, $data['client_id']);

        return $highest === null ? 0 : $highest + 1 - $number;
    }

    /**
     * The highest sequence number among the client's Shopify orders, if any.
     */
    private function highestShopifyNumber(string $prefix, int $clientId): ?int
    {
        return Order::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->where('source', 'shopify')
            ->pluck('order_number')
            ->map(fn (string $orderNumber) => $this->sequenceOf($prefix, $orderNumber))
            ->filter(fn (?int $sequence) => $sequence !== null)
            ->max();
    }

    /**
     * The numeric part of a portal number (TST1042 → 1042), or null.
     */
    private function sequenceOf(string $prefix, string $orderNumber): ?int
    {
        return preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $orderNumber, $m) ? (int) $m[1] : null;
    }

    private function taken(string $orderNumber): bool
    {
        return Order::withoutGlobalScopes()->where('order_number', $orderNumber)->exists();
    }

    /**
     * Keep the fulfillment state our courier flow has already reached.
     *
     * This used to be needed because Shopify never heard from us at all: an
     * order we had delivered was still unfulfilled there, so every routine
     * update carried a state older than ours and writing it verbatim flipped a
     * delivered order back to unfulfilled in the merchant's list.
     *
     * Now that fulfillments are reported (ShopifyFulfillmentService), that
     * particular disagreement is gone for stores on the fulfillment flow — but
     * the guard is if anything more necessary, not less. Two cases still need
     * it. A returned parcel cancels the Shopify fulfillment, which puts the
     * order back to *unfulfilled there* while ours is cancelled; and a store
     * that has not re-granted the fulfillment scopes, or whose catalogue still
     * imports as `manual`, behaves exactly as every store did before.
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

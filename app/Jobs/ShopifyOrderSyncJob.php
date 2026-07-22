<?php

namespace App\Jobs;

use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs once when a client first connects (and on reconnect) — pulls the last
 * 60 days of orders via GraphQL cursor pagination. Idempotent: updateOrCreate
 * keyed on shopify_order_id, so it is safe to re-run.
 */
class ShopifyOrderSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct(private int $connectionId) {}

    public function handle(ShopifyService $shopify): void
    {
        $connection = ClientShopifyConnection::with('client')->find($this->connectionId);

        if (! $connection || ! $connection->client || $connection->status !== 'active') {
            return;
        }

        try {
            $token = $shopify->getValidToken($connection);
        } catch (\Throwable $e) {
            Log::warning('Shopify sync aborted — token invalid', [
                'connection' => $connection->id, 'error' => $e->getMessage(),
            ]);
            return; // connection already flagged 'error' by getValidToken
        }

        $cursor = null;

        try {
            do {
                $page = $shopify->fetchRecentOrders($connection->shop_domain, $token, $cursor);

                foreach ($page['orders'] as $node) {
                    $data = $shopify->mapGraphqlOrder($node, $connection->client, $connection->shop_domain);

                    $existing = Order::withoutGlobalScope('shopify_visible')
                        ->where('shopify_order_id', $data['shopify_order_id'])
                        ->first();

                    $data['shopify_sync_status'] = $existing
                        ? $existing->shopify_sync_status
                        : $shopify->evaluateSyncFilters($data, $connection);

                    $order = Order::withoutGlobalScope('shopify_visible')->updateOrCreate(
                        ['shopify_order_id' => $data['shopify_order_id']],
                        $data
                    );

                    // Replace line items wholesale rather than upserting keyed
                    // on SKU: an order can have two line items sharing a SKU
                    // (or both with no SKU at all, which is common), and
                    // matching on lineitem_sku alone collapses them into one
                    // row, silently dropping the other. Nothing downstream
                    // depends on a Shopify-sourced item keeping a stable row
                    // id across syncs.
                    $order->items()->delete();
                    $order->items()->createMany($shopify->mapLineItems($node, 'graphql'));
                }

                $cursor = $page['endCursor'];
            } while ($page['hasNextPage']);

            $connection->update(['last_synced_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Shopify order sync failed', [
                'connection' => $connection->id,
                'shop'       => $connection->shop_domain,
                'error'      => $e->getMessage(),
            ]);

            // ACCESS_DENIED means the app lacks Protected Customer Data approval.
            // Retrying won't help — mark the connection so the UI can surface it.
            if (str_contains($e->getMessage(), 'ACCESS_DENIED')) {
                $connection->update(['status' => 'error']);
                return;
            }

            throw $e; // transient errors (network, rate-limit) are retried by the queue
        }
    }
}

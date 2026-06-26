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

        do {
            $page = $shopify->fetchRecentOrders($connection->shop_domain, $token, $cursor);

            foreach ($page['orders'] as $node) {
                $data = $shopify->mapGraphqlOrder($node, $connection->client);

                $existing = Order::withoutGlobalScope('shopify_visible')
                    ->where('shopify_order_id', $data['shopify_order_id'])
                    ->first();

                $data['shopify_sync_status'] = $existing
                    ? $existing->shopify_sync_status
                    : ($connection->sync_mode === 'manual_approval' ? 'pending_review' : null);

                $order = Order::withoutGlobalScope('shopify_visible')->updateOrCreate(
                    ['shopify_order_id' => $data['shopify_order_id']],
                    $data
                );

                $order->items()->delete();
                foreach ($shopify->mapLineItems($node, 'graphql') as $item) {
                    $order->items()->create($item);
                }
            }

            $cursor = $page['endCursor'];
        } while ($page['hasNextPage']);

        $connection->update(['last_synced_at' => now()]);
    }
}

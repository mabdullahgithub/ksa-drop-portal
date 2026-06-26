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

class ProcessShopifyWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        private string $shopDomain,
        private string $topic,
        private array  $payload,
    ) {}

    public function handle(ShopifyService $shopify): void
    {
        $connection = ClientShopifyConnection::with('client')
            ->where('shop_domain', $this->shopDomain)
            ->where('status', 'active')
            ->first();

        // Unknown / disconnected store — ignore silently.
        if (! $connection || ! $connection->client) {
            return;
        }

        match ($this->topic) {
            'orders/create', 'orders/updated', 'orders/paid' => $this->upsertOrder($shopify, $connection),
            'orders/cancelled' => $this->cancelOrder(),
            default            => null,
        };
    }

    private function upsertOrder(ShopifyService $shopify, ClientShopifyConnection $connection): void
    {
        $data = $shopify->mapWebhookOrder($this->payload, $connection->client);

        $existing = Order::withoutGlobalScope('shopify_visible')
            ->where('shopify_order_id', $data['shopify_order_id'])
            ->first();

        // Decide visibility:
        //  - existing order keeps its review decision (never re-queue an approved/dismissed one)
        //  - new order follows the connection's current sync mode
        if ($existing) {
            $data['shopify_sync_status'] = $existing->shopify_sync_status;
        } else {
            $data['shopify_sync_status'] = $connection->sync_mode === 'manual_approval'
                ? 'pending_review'
                : null;
        }

        $order = Order::withoutGlobalScope('shopify_visible')->updateOrCreate(
            ['shopify_order_id' => $data['shopify_order_id']],
            $data
        );

        // Re-sync line items (delete + recreate for simplicity/idempotency).
        $order->items()->delete();
        foreach ($shopify->mapLineItems($this->payload, 'webhook') as $item) {
            $order->items()->create($item);
        }

        $connection->update(['last_synced_at' => now()]);
    }

    private function cancelOrder(): void
    {
        $shopifyOrderId = (string) ($this->payload['id'] ?? '');

        if ($shopifyOrderId === '') {
            return;
        }

        Order::withoutGlobalScope('shopify_visible')
            ->where('shopify_order_id', $shopifyOrderId)
            ->update([
                'fulfillment_status' => 'cancelled',
                'cancelled_at'       => now(),
            ]);
    }
}

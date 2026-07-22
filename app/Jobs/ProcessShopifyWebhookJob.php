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
        // GDPR compliance topics must be handled even for disconnected stores —
        // shop/redact arrives 48h after uninstall, when no active connection exists.
        switch ($this->topic) {
            case 'app/uninstalled':
                $this->handleAppUninstalled();

                return;
            case 'customers/data_request':
                $this->logDataRequest();

                return;
            case 'customers/redact':
                $this->redactCustomer();

                return;
            case 'shop/redact':
                $this->redactShop();

                return;
        }

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

    /**
     * PII columns cleared when Shopify asks us to redact customer data.
     * Order totals, statuses and line items are kept — they are not personal data.
     */
    private const PII_REDACTIONS = [
        'customer_name'    => '[redacted]',
        'customer_email'   => null,
        'customer_phone'   => null,
        'billing_name'     => '[redacted]',
        'billing_street'   => null,
        'billing_address1' => null,
        'billing_address2' => null,
        'billing_company'  => null,
        'billing_city'     => null,
        'billing_zip'      => null,
        'billing_phone'    => null,
        'shipping_name'    => '[redacted]',
        'shipping_street'  => null,
        'shipping_address1' => null,
        'shipping_address2' => null,
        'shipping_company'  => null,
        'shipping_city'     => null,
        'shipping_zip'      => null,
        'shipping_phone'    => null,
        'ip_address'        => null,
    ];

    /**
     * customers/data_request — the merchant must supply the customer's data.
     * We surface it in the logs so the operator can respond within 30 days.
     */
    private function logDataRequest(): void
    {
        Log::channel('single')->info('Shopify GDPR customers/data_request received', [
            'shop'             => $this->shopDomain,
            'customer_id'      => $this->payload['customer']['id'] ?? null,
            'customer_email'   => $this->payload['customer']['email'] ?? null,
            'orders_requested' => $this->payload['orders_requested'] ?? [],
        ]);
    }

    /**
     * customers/redact — erase the customer's PII from the orders Shopify lists.
     */
    private function redactCustomer(): void
    {
        $orderIds = array_map('strval', $this->payload['orders_to_redact'] ?? []);

        if ($orderIds === []) {
            return;
        }

        $count = Order::withoutGlobalScope('shopify_visible')
            ->whereIn('shopify_order_id', $orderIds)
            ->update(self::PII_REDACTIONS);

        Log::info('Shopify GDPR customers/redact processed', [
            'shop'            => $this->shopDomain,
            'orders_redacted' => $count,
        ]);
    }

    /**
     * app/uninstalled — sent when merchant uninstalls the app. Disconnect the
     * connection by clearing tokens, but preserve order history so the merchant
     * can still see and fulfill existing orders. Syncing stops automatically
     * since new orders require a fresh access token.
     */
    private function handleAppUninstalled(): void
    {
        $connections = ClientShopifyConnection::where('shop_domain', $this->shopDomain)->get();

        foreach ($connections as $connection) {
            $connection->update([
                'access_token'  => null,
                'refresh_token' => null,
                'status'        => 'disconnected',
            ]);
        }

        Log::info('Shopify app/uninstalled processed', [
            'shop'        => $this->shopDomain,
            'connections' => $connections->count(),
        ]);
    }

    /**
     * shop/redact — sent 48h after uninstall: erase PII from all of the shop's
     * synced orders and drop the connection (and its stored tokens) entirely.
     */
    private function redactShop(): void
    {
        // Target the shop directly rather than the connection's client. The
        // link can legitimately be gone by now — a portal disconnect releases
        // client_id — and a client-scoped update would then match nothing and
        // silently erase no PII at all.
        $redacted = Order::withoutGlobalScope('shopify_visible')
            ->where('shopify_shop_domain', $this->shopDomain)
            ->whereNotNull('shopify_order_id')
            ->update(self::PII_REDACTIONS);

        $connections = ClientShopifyConnection::where('shop_domain', $this->shopDomain)->get();

        foreach ($connections as $connection) {
            $connection->delete();
        }

        Log::info('Shopify GDPR shop/redact processed', [
            'shop'            => $this->shopDomain,
            'connections'     => $connections->count(),
            'orders_redacted' => $redacted,
        ]);
    }

    private function upsertOrder(ShopifyService $shopify, ClientShopifyConnection $connection): void
    {
        $data = $shopify->mapWebhookOrder($this->payload, $connection->client, $connection->shop_domain);

        $existing = Order::withoutGlobalScope('shopify_visible')
            ->where('shopify_order_id', $data['shopify_order_id'])
            ->first();

        // Decide visibility:
        //  - existing order keeps its review decision (never re-queue an approved/dismissed one)
        //  - new order is checked against the merchant's sync filters, then sync mode
        if ($existing) {
            $data['shopify_sync_status'] = $existing->shopify_sync_status;
        } else {
            $data['shopify_sync_status'] = $shopify->evaluateSyncFilters($data, $connection);
        }

        $order = Order::withoutGlobalScope('shopify_visible')->updateOrCreate(
            ['shopify_order_id' => $data['shopify_order_id']],
            $data
        );

        // Replace line items wholesale rather than upserting keyed on SKU:
        // an order can have two line items sharing a SKU (or both with no
        // SKU at all, which is common), and matching on lineitem_sku alone
        // collapses them into one row, silently dropping the other. Nothing
        // downstream depends on a Shopify-sourced item keeping a stable row
        // id across syncs, so delete-and-reinsert is both correct and simpler.
        $order->items()->delete();
        $order->items()->createMany($shopify->mapLineItems($this->payload, 'webhook'));

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

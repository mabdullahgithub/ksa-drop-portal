<?php

namespace App\Jobs;

use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Models\ShopifySyncFailure;
use App\Services\ShopifyOrderWriter;
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

    /**
     * Topics worth parking in shopify_sync_failures when they don't go through.
     * Everything else this job sees is either a no-op (an unhandled topic) or
     * pure logging (customers/data_request) — replaying those buys nothing.
     */
    private const RETRYABLE_TOPICS = [
        'orders/create',
        'orders/updated',
        'orders/paid',
        'orders/cancelled',
        'customers/redact',
        'shop/redact',
        'app/uninstalled',
    ];

    /**
     * @param  int|null  $failureId  Set when this dispatch is a replay of a
     *                               parked failure. The retry sweep owns that
     *                               row's attempt budget, so a replay updates
     *                               the row instead of re-recording it.
     */
    public function __construct(
        private string $shopDomain,
        private string $topic,
        private array  $payload,
        private ?int   $failureId = null,
    ) {}

    /** Set when this run parked the delivery instead of completing it. */
    private bool $parked = false;

    private ShopifyOrderWriter $writer;

    public function handle(ShopifyService $shopify, ShopifyOrderWriter $writer): void
    {
        $this->writer = $writer;

        $this->processTopic($shopify);

        // Reaching here without parking means the delivery went through, so a
        // replay's row is done. Handled centrally rather than in each topic
        // handler so every path — order upsert, cancellation, GDPR redaction —
        // clears its row the same way.
        if ($this->failureId !== null && ! $this->parked) {
            ShopifySyncFailure::whereKey($this->failureId)->update([
                'status'      => ShopifySyncFailure::STATUS_RESOLVED,
                'resolved_at' => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    private function processTopic(ShopifyService $shopify): void
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

        // Unknown / disconnected store, or a connection with no client linked
        // yet. Log it: an order webhook landing here is a real "why didn't it
        // sync" cause (e.g. the store was disconnected, or claimed but the row
        // has no client), and silence made that indistinguishable from success.
        if (! $connection || ! $connection->client) {
            // Park it rather than drop it. A store is routinely unclaimed for
            // the first few minutes after install, and every order that lands
            // in that window used to be lost for good — the merchant saw it in
            // Shopify and never in the portal. Parked, it replays on the retry
            // sweep and immediately on claim.
            $this->parkFailure(
                ShopifySyncFailure::REASON_NO_CONNECTION,
                $connection ? 'Connection has no client linked yet.' : 'No active connection for this shop.',
            );

            Log::channel('shopify')->warning('Shopify webhook parked — no active linked connection', [
                'shop'             => $this->shopDomain,
                'topic'            => $this->topic,
                'connection_found' => (bool) $connection,
            ]);

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
        Log::channel('shopify')->info('Shopify GDPR customers/data_request received', [
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

        Log::channel('shopify')->info('Shopify GDPR customers/redact processed', [
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
                // Shopify deletes every webhook subscription on uninstall, so
                // the flag has to go false too. Leaving it true made the next
                // reinstall skip re-registration (ensureInstalled only armed
                // webhooks when the flag was false), so live sync silently
                // never came back — the store looked connected but received no
                // order webhooks at all.
                'webhooks_registered' => false,
            ]);
        }

        Log::channel('shopify')->info('Shopify app/uninstalled processed', [
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

        Log::channel('shopify')->info('Shopify GDPR shop/redact processed', [
            'shop'            => $this->shopDomain,
            'connections'     => $connections->count(),
            'orders_redacted' => $redacted,
        ]);
    }

    private function upsertOrder(ShopifyService $shopify, ClientShopifyConnection $connection): void
    {
        $data = $shopify->mapWebhookOrder($this->payload, $connection->client, $connection->shop_domain);

        // The write itself — review status, locally-owned fulfillment, item
        // replacement, atomicity — lives in ShopifyOrderWriter, shared with the
        // reconciliation poll so the two paths cannot drift apart.
        $this->writer->write($data, $shopify->mapLineItems($this->payload, 'webhook'), $connection);

        $connection->update(['last_synced_at' => now()]);

        Log::channel('shopify')->info('Shopify order synced from webhook', [
            'shop'         => $connection->shop_domain,
            'topic'        => $this->topic,
            'order_number' => $data['order_number'] ?? null,
            'sync_status'  => $data['shopify_sync_status'] ?? 'processed',
        ]);
    }

    /**
     * Last stop after the queue has burned every attempt: park the delivery
     * with the payload that produced it so it can be replayed later, instead of
     * letting it die into failed_jobs where nothing surfaces or drains it.
     */
    public function failed(?\Throwable $e): void
    {
        if (! in_array($this->topic, self::RETRYABLE_TOPICS, true)) {
            return;
        }

        $this->parkFailure(
            ShopifySyncFailure::REASON_EXCEPTION,
            $e ? $e::class . ': ' . $e->getMessage() : 'Job failed with no exception reported.',
        );

        Log::channel('shopify')->error('Shopify webhook sync failed — parked for retry', [
            'shop'  => $this->shopDomain,
            'topic' => $this->topic,
            'error' => $e?->getMessage(),
        ]);
    }

    /**
     * Record (or update) this delivery's dead-letter row.
     */
    private function parkFailure(string $reason, ?string $message): void
    {
        if (! in_array($this->topic, self::RETRYABLE_TOPICS, true)) {
            return;
        }

        $this->parked = true;

        // A replay already has a row, and the retry sweep owns its attempt
        // budget. Re-recording would reopen it with a fresh budget, so a payload
        // that can never succeed would retry forever instead of being given up
        // on after MAX_ATTEMPTS.
        if ($this->failureId !== null) {
            ShopifySyncFailure::whereKey($this->failureId)->update([
                'reason'        => $reason,
                'error_message' => $message !== null ? mb_substr($message, 0, 2000) : null,
                'updated_at'    => now(),
            ]);

            return;
        }

        ShopifySyncFailure::record(
            $this->shopDomain,
            $this->topic,
            $this->payload,
            $reason,
            $message,
            ClientShopifyConnection::where('shop_domain', $this->shopDomain)->value('client_id'),
        );
    }

    private function cancelOrder(): void
    {
        $shopifyOrderId = (string) ($this->payload['id'] ?? '');

        if ($shopifyOrderId === '') {
            return;
        }

        // now() only as a last resort, and never over a timestamp we already
        // have: a second delivery of this topic — Shopify's own retry, or a
        // replay — would otherwise walk the recorded cancellation time forward
        // to whenever the replay happened to run. Same rule the shipment side
        // already follows (Shipment::markReturned).
        Order::withoutGlobalScope('shopify_visible')
            ->where('shopify_order_id', $shopifyOrderId)
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => $this->payload['cancelled_at'] ?? now()]);

        Order::withoutGlobalScope('shopify_visible')
            ->where('shopify_order_id', $shopifyOrderId)
            ->update(['fulfillment_status' => 'cancelled']);
    }
}

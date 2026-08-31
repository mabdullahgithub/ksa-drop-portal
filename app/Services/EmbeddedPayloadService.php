<?php

namespace App\Services;

use App\Models\ClientShopifyConnection;
use App\Models\Order;

/**
 * Builds the JSON payloads the embedded Shopify app renders from.
 *
 * Extracted from EmbeddedDashboardController / EmbeddedSettingsController so
 * the Blade shell can inline the same payload on the initial load without
 * duplicating the queries. Both callers must produce byte-identical JSON —
 * the frontend cannot tell which path a payload arrived by.
 */
class EmbeddedPayloadService
{
    /** Statuses offered by the settings UI and accepted by validation. */
    public const FINANCIAL_STATUSES   = ['paid', 'pending', 'refunded', 'partially_refunded', 'partially_paid', 'voided'];

    public const FULFILLMENT_STATUSES = ['fulfilled', 'unfulfilled', 'cancelled'];

    public function dashboard(ClientShopifyConnection $connection): array
    {
        $counts = Order::withoutGlobalScope('shopify_visible')
            ->where('client_id', $connection->client_id)
            ->where('source', 'shopify')
            ->selectRaw("COALESCE(shopify_sync_status, 'processed') as status, COUNT(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        // Orders per day, last 30 days, missing days filled with zero.
        $from = now()->subDays(29)->startOfDay();

        $perDay = Order::withoutGlobalScope('shopify_visible')
            ->where('client_id', $connection->client_id)
            ->where('source', 'shopify')
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $dailyOrders = [];

        for ($day = $from->copy(); $day->lte(now()); $day->addDay()) {
            $key = $day->toDateString();
            $dailyOrders[] = ['date' => $key, 'orders' => (int) ($perDay[$key] ?? 0)];
        }

        $recentOrders = Order::withoutGlobalScope('shopify_visible')
            ->where('client_id', $connection->client_id)
            ->where('source', 'shopify')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get([
                'id',
                'order_number',
                'customer_name',
                'financial_status',
                'fulfillment_status',
                'payment_method',
                'shopify_sync_status',
                'currency',
                'total',
                'created_at',
            ]);

        return [
            'shop_domain'    => $connection->shop_domain,
            'last_synced_at' => $connection->last_synced_at?->toISOString(),
            'stats'          => [
                // 'processed' = visible orders (null status) + approved ones
                'processed'      => (int) ($counts['processed'] ?? 0) + (int) ($counts['approved'] ?? 0),
                'pending_review' => (int) ($counts['pending_review'] ?? 0),
                'skipped'        => (int) ($counts['skipped_filtered'] ?? 0),
                'dismissed'      => (int) ($counts['dismissed'] ?? 0),
                'total'          => (int) $counts->sum(),
            ],
            'daily_orders'   => $dailyOrders,
            'recent_orders'  => $recentOrders,
        ];
    }

    public function settings(ClientShopifyConnection $connection): array
    {
        return [
            'sync_mode'    => $connection->sync_mode,
            'sync_filters' => $this->filtersWithDefaults($connection->sync_filters ?? []),
        ];
    }

    public function filtersWithDefaults(array $filters): array
    {
        return [
            'financial_statuses'   => array_values($filters['financial_statuses'] ?? []),
            'fulfillment_statuses' => array_values($filters['fulfillment_statuses'] ?? []),
            'tags_include'         => array_values($filters['tags_include'] ?? []),
            'tags_exclude'         => array_values($filters['tags_exclude'] ?? []),
            'payment_method'       => $filters['payment_method'] ?? 'all',
        ];
    }
}

<?php

namespace App\Http\Controllers\Embedded;

use App\Http\Controllers\Controller;
use App\Models\ClientShopifyConnection;
use App\Models\Order;
use Illuminate\Http\Request;

class EmbeddedDashboardController extends Controller
{
    /**
     * Sync stats + recent orders for the embedded dashboard. The connection is
     * resolved by the shopify.session middleware from the session token.
     */
    public function index(Request $request)
    {
        /** @var ClientShopifyConnection $connection */
        $connection = $request->attributes->get('shopify_connection');

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

        return response()->json([
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
        ]);
    }
}

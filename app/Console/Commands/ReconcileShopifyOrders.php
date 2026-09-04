<?php

namespace App\Console\Commands;

use App\Models\ClientShopifyConnection;
use App\Services\ShopifyOrderWriter;
use App\Services\ShopifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pull recent orders from the Admin API and import whatever is missing.
 *
 * This is the safety net for orders that never reached us at all — the case the
 * dead-letter queue is blind to by construction. Shopify gives a failed webhook
 * delivery 8 attempts over 4 hours and then drops it, and removes the
 * subscription outright after repeated failures in a 24-hour period. In both
 * cases nothing ever hits our endpoint, so nothing is parked and nothing shows
 * up in the portal's failed-sync panel: from inside the app the order simply
 * does not exist. Going and asking Shopify is the only way to find out.
 *
 * Deliberately additive: an order we already hold in any state — including the
 * hidden ones (pending_review, dismissed, skipped_filtered) — is left alone, so
 * this never resurrects an order the merchant dismissed or the sync filters
 * skipped, and never overwrites local fulfillment.
 *
 * Orders the store's sync filters reject are not imported at all — not even as
 * a hidden skipped_filtered row. Filters are the merchant's decision about what
 * belongs in the portal, and a missed delivery is no reason to revisit it.
 */
class ReconcileShopifyOrders extends Command
{
    protected $signature = 'shopify:reconcile-orders
                            {--hours=6 : How far back to look}
                            {--shop= : Only reconcile this shop domain}
                            {--dry-run : Report what is missing without importing it}';

    protected $description = 'Import Shopify orders that were never delivered by webhook';

    /** Safety valve: stop paging rather than walk an entire order history. */
    private const MAX_PAGES = 40;

    public function handle(ShopifyService $shopify, ShopifyOrderWriter $writer): int
    {
        $since = now()->subHours(max(1, (int) $this->option('hours')));
        $dryRun = (bool) $this->option('dry-run');

        $query = ClientShopifyConnection::with('client')
            ->where('status', 'active')
            ->whereNotNull('client_id')
            ->when($this->option('shop'), fn ($q, $shop) => $q->where('shop_domain', $shop));

        $stores = $query->count();

        if ($stores === 0) {
            $this->info('No active, client-linked Shopify connections to reconcile.');

            return self::SUCCESS;
        }

        $totalImported = 0;
        $totalScanned  = 0;

        // Chunked so memory stays flat as stores are added; each store is an
        // independent round of API calls regardless.
        $query->chunkById(100, function ($connections) use ($shopify, $writer, $since, $dryRun, &$totalScanned, &$totalImported) {
            foreach ($connections as $connection) {
                if ($connection->client === null) {
                    continue;
                }

                [$scanned, $imported] = $this->reconcileConnection($shopify, $writer, $connection, $since, $dryRun);

                $totalScanned  += $scanned;
                $totalImported += $imported;
            }
        });

        $verb = $dryRun ? 'missing' : 'imported';
        $this->info("Scanned {$totalScanned} order(s) across {$stores} store(s); {$totalImported} {$verb}.");

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int} [orders scanned, orders imported]
     */
    private function reconcileConnection(
        ShopifyService $shopify,
        ShopifyOrderWriter $writer,
        ClientShopifyConnection $connection,
        \Illuminate\Support\Carbon $since,
        bool $dryRun,
    ): array {
        $shop = $connection->shop_domain;

        try {
            $token = $shopify->getValidToken($connection);
        } catch (\Throwable $e) {
            // A store whose token cannot be renewed needs the merchant to
            // reopen the app; skip it rather than failing the whole sweep.
            $this->warn("  {$shop}: skipped — {$e->getMessage()}");

            return [0, 0];
        }

        $scanned  = 0;
        $imported = 0;
        $filtered = 0;
        $cursor   = null;
        $pages    = 0;

        do {
            try {
                $page = $shopify->fetchOrdersSince($shop, $token, $since, $cursor);
            } catch (\Throwable $e) {
                Log::channel('shopify')->error('Shopify reconciliation fetch failed', [
                    'shop'  => $shop,
                    'error' => $e->getMessage(),
                ]);

                $this->warn("  {$shop}: fetch failed — {$e->getMessage()}");

                break;
            }

            // Map the page up front, then ask once which of them we already
            // hold — one query per page instead of one per order.
            $mapped = array_map(
                fn (array $node) => [$node, $shopify->mapGraphqlOrder($node, $connection->client, $shop)],
                $page['orders'],
            );

            $held = $writer->existing(array_column(array_column($mapped, 1), 'shopify_order_id'));

            foreach ($mapped as [$node, $data]) {
                $scanned++;

                if (isset($held[$data['shopify_order_id']])) {
                    continue;
                }

                // The merchant's sync filters are a decision about which orders
                // they want in the portal at all — not a delivery problem. An
                // order that fails them was never meant to be imported, so
                // reconciliation leaves it in Shopify rather than creating a
                // hidden skipped_filtered row for it.
                //
                // This is deliberately unlike the webhook path, which does
                // record the skip: there the order arrived and the row is the
                // receipt for having seen and rejected it. Here nothing arrived,
                // so there is nothing to acknowledge — and re-evaluating the
                // filters on each sweep means loosening them later lets these
                // orders in, instead of leaving them permanently marked.
                //
                // Only skipped_filtered is dropped. A manual-approval store's
                // pending_review orders are still imported: those belong in the
                // review queue, which is exactly where the merchant expects to
                // find them.
                if ($shopify->evaluateSyncFilters($data, $connection) === 'skipped_filtered') {
                    $filtered++;

                    continue;
                }

                $imported++;

                $this->line("  · {$shop} missing order {$data['order_number']}");

                if ($dryRun) {
                    continue;
                }

                // Line items are fetched only now, for an order we have
                // actually decided to import — they are far too expensive to
                // select inline for every order in the listing.
                $node['lineItems'] = $shopify->fetchOrderLineItems($shop, $token, $node['id']);

                $writer->write($data, $shopify->mapLineItems($node, 'graphql'), $connection);

                Log::channel('shopify')->warning('Shopify order recovered by reconciliation', [
                    'shop'             => $shop,
                    'order_number'     => $data['order_number'],
                    'shopify_order_id' => $data['shopify_order_id'],
                ]);
            }

            $cursor = $page['cursor'];
        } while ($cursor !== null && ++$pages < self::MAX_PAGES);

        if ($filtered > 0) {
            $this->line("  · {$shop}: {$filtered} order(s) left in Shopify by the store's sync filters");
        }

        if ($imported > 0 && ! $dryRun) {
            $connection->update(['last_synced_at' => now()]);
        }

        return [$scanned, $imported];
    }
}

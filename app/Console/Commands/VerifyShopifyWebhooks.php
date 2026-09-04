<?php

namespace App\Console\Commands;

use App\Models\ClientShopifyConnection;
use App\Services\ShopifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Check that Shopify still holds a webhook subscription for every topic we
 * depend on, and re-register the ones it has dropped.
 *
 * Shopify removes a subscription after repeated delivery failures in a 24-hour
 * period and never tells us. Our stored webhooks_registered flag stays true,
 * ensureInstalled() returns early while the access token is still healthy, and
 * the store simply stops sending orders — with no failed syncs to show for it,
 * because nothing arrives to fail. Until now the only recovery was the merchant
 * noticing and pressing "Retry webhooks" in the portal.
 *
 * A subscription pointing at a stale callback URL (an old tunnel, a previous
 * domain) is treated as missing: it is just as broken, and re-registering
 * rewrites it to the current endpoint.
 */
class VerifyShopifyWebhooks extends Command
{
    protected $signature = 'shopify:verify-webhooks
                            {--shop= : Only check this shop domain}
                            {--dry-run : Report drift without re-registering}';

    protected $description = 'Verify Shopify still holds our webhook subscriptions, and restore any it dropped';

    public function handle(ShopifyService $shopify): int
    {
        $expectedUrl = $shopify->webhookCallbackUrl();

        $query = ClientShopifyConnection::where('status', 'active')
            ->when($this->option('shop'), fn ($q, $shop) => $q->where('shop_domain', $shop));

        $total = $query->count();

        if ($total === 0) {
            $this->info('No active Shopify connections to check.');

            return self::SUCCESS;
        }

        $repaired = 0;

        // Chunked so the sweep's memory stays flat as the number of connected
        // stores grows; each one is an independent round of API calls anyway.
        $query->chunkById(100, function ($connections) use ($expectedUrl, $shopify, &$repaired) {
            foreach ($connections as $connection) {
            $shop = $connection->shop_domain;

            try {
                $token = $shopify->getValidToken($connection);
                $held  = $shopify->listWebhookSubscriptions($shop, $token);
            } catch (\Throwable $e) {
                $this->warn("  {$shop}: skipped — {$e->getMessage()}");

                continue;
            }

            $missing = array_values(array_filter(
                ShopifyService::WEBHOOK_TOPICS,
                fn (string $topic) => ($held[$topic] ?? null) !== $expectedUrl,
            ));

            if ($missing === []) {
                $connection->update(['webhooks_registered' => true]);
                $this->line("  · {$shop}: all " . count(ShopifyService::WEBHOOK_TOPICS) . ' subscriptions present');

                continue;
            }

            // Flag it first. If the re-registration below fails, the connection
            // must not keep claiming its webhooks are healthy — the portal shows
            // this flag, and it is what tells the merchant to press "Retry
            // webhooks" when automatic repair could not.
            $connection->update(['webhooks_registered' => false]);

            $this->warn("  · {$shop}: missing " . implode(', ', $missing));

            Log::channel('shopify')->error('Shopify webhook subscriptions missing', [
                'shop'    => $shop,
                'missing' => $missing,
                'held'    => array_keys($held),
            ]);

            if ($this->option('dry-run')) {
                continue;
            }

            // registerWebhooks is idempotent — a subscription that does exist
            // comes back as "already taken" and counts as success — so it is
            // safe to re-run for every topic rather than only the missing ones.
            $results = $shopify->registerWebhooks($shop, $token, $errors);

            if (! in_array(false, $results, true)) {
                $connection->update(['webhooks_registered' => true]);
                $repaired++;

                $this->info("    re-registered {$shop}");

                Log::channel('shopify')->info('Shopify webhook subscriptions restored', ['shop' => $shop]);

                continue;
            }

            $this->error("    could not re-register {$shop}: " . json_encode($errors));
            }
        });

        $this->info("Checked {$total} store(s); repaired {$repaired}.");

        return self::SUCCESS;
    }
}

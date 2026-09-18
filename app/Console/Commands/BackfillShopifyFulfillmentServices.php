<?php

namespace App\Console\Commands;

use App\Models\ClientShopifyConnection;
use App\Services\ShopifyFulfillmentService;
use Illuminate\Console\Command;

/**
 * Register the KSADrop fulfillment service on stores that connected before the
 * fulfillment scopes existed.
 *
 * Registration normally happens at OAuth (ShopifyController::armConnection) or
 * on the next embedded app load (EmbeddedAppController::claimToken). Neither
 * reaches a store whose merchant has not opened the app since the scopes
 * changed — managed installation only re-prompts when they do — and a store
 * with no KSADrop location produces no fulfillment orders for us at all, while
 * looking perfectly healthy: orders keep syncing, webhooks keep arriving.
 *
 * So this sweep is the answer to "the merchant says nothing is coming through
 * for fulfillment". It is also safe to run at any time: connections that are
 * already registered, or still missing the scopes, are skipped without a single
 * API call.
 */
class BackfillShopifyFulfillmentServices extends Command
{
    protected $signature = 'shopify:backfill-fulfillment-services
                            {--shop= : Only this shop domain}
                            {--dry-run : List what would be registered, without calling Shopify}';

    protected $description = 'Register the KSADrop fulfillment service on connected stores that lack one';

    public function handle(ShopifyFulfillmentService $fulfillment): int
    {
        if (! $fulfillment->enabled()) {
            $this->warn('SHOPIFY_FULFILLMENT_ENABLED is off — nothing to do.');

            return self::SUCCESS;
        }

        $query = ClientShopifyConnection::where('status', 'active')
            ->whereNull('fulfillment_service_id')
            ->when($this->option('shop'), fn ($q, $shop) => $q->where('shop_domain', $shop));

        if ($query->clone()->count() === 0) {
            $this->info('Every active connection already has a fulfillment service.');

            return self::SUCCESS;
        }

        $registered = $waiting = $failed = 0;

        $query->chunkById(100, function ($connections) use ($fulfillment, &$registered, &$waiting, &$failed) {
            foreach ($connections as $connection) {
                $shop = $connection->shop_domain;

                // Reported separately from a failure: there is nothing wrong
                // with this store and nothing to fix on our side. It is waiting
                // on the merchant to open the app and approve the new scopes.
                if (! $connection->hasFulfillmentScopes()) {
                    $this->line("  {$shop}: waiting on the merchant to re-approve scopes");
                    $waiting++;

                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line("  {$shop}: would register");
                    $registered++;

                    continue;
                }

                if ($fulfillment->ensureRegistered($connection)) {
                    $this->info("  {$shop}: registered");
                    $registered++;
                } else {
                    $this->warn("  {$shop}: failed — see the shopify log");
                    $failed++;
                }
            }
        });

        $verb = $this->option('dry-run') ? 'would register' : 'registered';
        $this->newLine();
        $this->info("{$verb}: {$registered}, waiting on re-approval: {$waiting}, failed: {$failed}");

        return self::SUCCESS;
    }
}

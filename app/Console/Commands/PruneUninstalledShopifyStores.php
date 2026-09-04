<?php

namespace App\Console\Commands;

use App\Models\ClientShopifyConnection;
use App\Services\ShopifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Find connections whose store has actually uninstalled the app, and close them.
 *
 * app/uninstalled is the webhook that is supposed to tell us, and it is the one
 * our own delivery stats say fails most often — 6 of 8 over one recent week. A
 * failed uninstall notice leaves the row `active` with a token Shopify has
 * already revoked: the merchant is gone, the portal still shows the store as
 * connected, and every background call made with that token is wasted.
 *
 * So this asks Shopify directly rather than waiting to be told. The cleanup it
 * applies is exactly what ProcessShopifyWebhookJob::handleAppUninstalled() does
 * — tokens cleared, status disconnected, webhooks_registered false — so a store
 * repaired here is indistinguishable from one that reported its own uninstall,
 * and a reinstall re-registers webhooks the same way.
 *
 * Orders are never touched. A merchant who uninstalls keeps their order history
 * in the portal, and reinstalling picks up where they left off.
 */
class PruneUninstalledShopifyStores extends Command
{
    protected $signature = 'shopify:prune-uninstalled
                            {--dry-run : Report what would be closed without changing anything}
                            {--shop= : Only check this shop domain}';

    protected $description = 'Close Shopify connections whose store has uninstalled the app';

    public function handle(ShopifyService $shopify): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $connections = ClientShopifyConnection::where('status', '!=', 'disconnected')
            ->whereNotNull('access_token')
            ->when($this->option('shop'), fn ($q, $shop) => $q->where('shop_domain', $shop))
            ->orderBy('id')
            ->get();

        if ($connections->isEmpty()) {
            $this->info('No connected Shopify stores to check.');

            return self::SUCCESS;
        }

        $this->info("Checking {$connections->count()} store(s)...");

        $installed = $undetermined = $closed = 0;

        foreach ($connections as $connection) {
            $shop = $connection->shop_domain;

            // Renews the grant before testing it. Our stored access tokens are
            // the short-lived kind and are normally already expired, and an
            // expired token is refused with the same 401 as a revoked one — so
            // spending the stored token directly reported every store on the
            // account as uninstalled, live merchants included.
            $state = $shopify->determineInstallState($connection);

            if ($state === true) {
                $installed++;
                continue;
            }

            if ($state === null) {
                $undetermined++;
                $this->line("  ? {$shop} — could not determine, leaving as is");
                continue;
            }

            $closed++;
            $this->warn("  ✗ {$shop} — uninstalled (client " . ($connection->client_id ?? 'unclaimed') . ')');

            if ($dryRun) {
                continue;
            }

            $connection->update([
                'access_token'  => null,
                'refresh_token' => null,
                'status'        => 'disconnected',
                // Shopify drops every subscription on uninstall, so the flag has
                // to follow or a reinstall would skip re-registration and come
                // back connected but silent.
                'webhooks_registered' => false,
            ]);

            Log::channel('shopify')->info('Shopify connection closed — store had uninstalled', [
                'shop'      => $shop,
                'client_id' => $connection->client_id,
            ]);
        }

        $verb = $dryRun ? 'would be closed' : 'closed';
        $this->info("Still installed: {$installed}. Undetermined: {$undetermined}. Uninstalled and {$verb}: {$closed}.");

        return self::SUCCESS;
    }
}

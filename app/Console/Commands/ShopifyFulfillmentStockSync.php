<?php

namespace App\Console\Commands;

use App\Models\ClientShopifyConnection;
use App\Services\ShopifyFulfillmentService;
use Illuminate\Console\Command;

/**
 * Switch the KSADrop location's stock between "reported by the portal" and
 * "typed by the merchant".
 *
 * On, Shopify takes the location's on-hand stock from /fetch_stock — for a SKU
 * when a product is set up, and for everything hourly. That is the only way
 * real stock reaches the location: Shopify ignores the product CSV's quantity
 * column on any store with more than one location, and a KSADrop store always
 * has at least two, so every import otherwise lands at 0.
 *
 * A command rather than something every app load does, because it changes
 * where a live store's stock figures come from and Shopify does not document
 * whether a CSV import triggers the lookup. Prove it on one store with --shop,
 * then roll it out; --disable puts a store back.
 */
class ShopifyFulfillmentStockSync extends Command
{
    protected $signature = 'shopify:fulfillment-stock-sync
                            {--enable : Take KSADrop location stock from the portal}
                            {--disable : Hand KSADrop location stock back to the merchant}
                            {--shop= : Only this shop domain}';

    protected $description = 'Turn portal stock reporting for the KSADrop location on or off';

    public function handle(ShopifyFulfillmentService $fulfillment): int
    {
        if ($this->option('enable') === $this->option('disable')) {
            $this->error('Pass exactly one of --enable or --disable.');

            return self::INVALID;
        }

        if (! $fulfillment->enabled()) {
            $this->warn('SHOPIFY_FULFILLMENT_ENABLED is off — nothing to do.');

            return self::SUCCESS;
        }

        $enable = (bool) $this->option('enable');

        $connections = ClientShopifyConnection::where('status', 'active')
            ->whereNotNull('fulfillment_service_id')
            ->when($this->option('shop'), fn ($q, $shop) => $q->where('shop_domain', $shop))
            ->get();

        if ($connections->isEmpty()) {
            $this->warn('No active connection with a KSADrop service matched.');

            return self::SUCCESS;
        }

        $done = $failed = 0;

        foreach ($connections as $connection) {
            if ($fulfillment->setStockSync($connection, $enable)) {
                $this->info("  {$connection->shop_domain}: stock sync " . ($enable ? 'enabled' : 'disabled'));
                $done++;
            } else {
                $this->warn("  {$connection->shop_domain}: failed — see the shopify log");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("updated: {$done}, failed: {$failed}");

        return self::SUCCESS;
    }
}

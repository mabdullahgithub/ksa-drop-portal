<?php

namespace App\Console\Commands;

use App\Models\ShopifySyncFailure;
use Illuminate\Console\Command;

/**
 * Prune settled Shopify sync failures.
 *
 * Every parked row holds the full webhook body that produced it — an order
 * payload runs from a few KB to well over a hundred with enough line items — and
 * once a row is resolved that payload has no further use: the order is in the
 * portal and nothing will ever replay it again. Without pruning the table only
 * grows, and it grows fastest exactly when something is going wrong.
 *
 * Abandoned rows are kept much longer than resolved ones. They are the record of
 * orders that never made it, which is the thing an operator goes looking for
 * weeks later.
 */
class CleanupShopifySyncFailures extends Command
{
    protected $signature = 'shopify:cleanup-sync-failures
                            {--resolved-days=14 : Keep resolved failures this long}
                            {--abandoned-days=90 : Keep abandoned failures this long}';

    protected $description = 'Delete settled Shopify sync failures and the webhook payloads they hold';

    public function handle(): int
    {
        $resolvedDays  = max(1, (int) $this->option('resolved-days'));
        $abandonedDays = max(1, (int) $this->option('abandoned-days'));

        // Keyed on resolved_at rather than created_at: a failure parked weeks
        // ago and only just replayed successfully should age out from when it
        // was settled, not from when it first broke.
        $resolved = ShopifySyncFailure::where('status', ShopifySyncFailure::STATUS_RESOLVED)
            ->where('resolved_at', '<', now()->subDays($resolvedDays))
            ->delete();

        $abandoned = ShopifySyncFailure::where('status', ShopifySyncFailure::STATUS_ABANDONED)
            ->where('updated_at', '<', now()->subDays($abandonedDays))
            ->delete();

        $this->info("Deleted {$resolved} resolved and {$abandoned} abandoned sync failure(s).");

        return self::SUCCESS;
    }
}

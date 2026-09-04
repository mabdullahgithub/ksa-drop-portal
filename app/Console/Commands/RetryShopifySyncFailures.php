<?php

namespace App\Console\Commands;

use App\Jobs\ProcessShopifyWebhookJob;
use App\Models\ShopifySyncFailure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Drain the Shopify dead-letter queue: replay every parked webhook whose
 * backoff has elapsed.
 *
 * Runs on the scheduler (see routes/console.php). Replays go back through
 * ProcessShopifyWebhookJob with the original payload, so a retry follows exactly
 * the same path as the live delivery did — sync filters, manual-approval mode
 * and line-item handling all included — and the job settles its own row: cleared
 * on success, re-parked with the new error on failure.
 */
class RetryShopifySyncFailures extends Command
{
    protected $signature = 'shopify:retry-failed-syncs
                            {--limit=100 : Maximum failures to replay in one sweep}
                            {--shop= : Only replay failures for this shop domain}';

    protected $description = 'Replay Shopify webhooks that failed to sync into orders';

    public function handle(): int
    {
        $failures = ShopifySyncFailure::due()
            ->when($this->option('shop'), fn ($q, $shop) => $q->where('shop_domain', $shop))
            // Oldest first: a merchant chasing a missing order cares about the
            // one that has been stuck longest, and it keeps a burst of new
            // failures from starving the backlog.
            ->orderBy('next_attempt_at')
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($failures->isEmpty()) {
            $this->info('No Shopify sync failures due for retry.');

            return self::SUCCESS;
        }

        $this->info("Replaying {$failures->count()} Shopify sync failure(s)...");

        foreach ($failures as $failure) {
            // Count the attempt before dispatching, never after: if the replay
            // takes the worker down with it, the attempt still has to be spent
            // or this row would be retried forever.
            $failure->registerAttempt();

            ProcessShopifyWebhookJob::dispatch(
                $failure->shop_domain,
                $failure->topic,
                $failure->payload,
                $failure->id,
            );

            $this->line("  · {$failure->shop_domain} {$failure->topic} "
                . ($failure->order_number ?? $failure->shopify_order_id ?? '—')
                . " (attempt {$failure->attempts}/" . ShopifySyncFailure::MAX_ATTEMPTS . ')');
        }

        $abandoned = $failures->where('status', ShopifySyncFailure::STATUS_ABANDONED)->count();

        Log::channel('shopify')->info('Shopify sync failure retry sweep', [
            'replayed'  => $failures->count(),
            'abandoned' => $abandoned,
        ]);

        if ($abandoned > 0) {
            $this->warn("{$abandoned} failure(s) reached the final attempt and will not be retried automatically again.");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\InventoryRequest;
use App\Services\Inventory\InventoryRequestRefused;
use App\Services\Inventory\InventoryRequestReviewer;
use Illuminate\Console\Command;

/**
 * One-off, for the change that stopped detail edits needing approval: applies
 * the detail requests still waiting from before it. Each client is told their
 * change was approved.
 */
class ApplyPendingDetailRequests extends Command
{
    protected $signature = 'inventory:apply-pending-details {--dry-run : List them without changing anything}';

    protected $description = 'Apply product detail changes that were still waiting for approval';

    public function handle(InventoryRequestReviewer $reviewer): int
    {
        $requests = InventoryRequest::pending()
            ->where('type', InventoryRequest::DETAILS)
            ->with(['product', 'client'])
            ->orderBy('id')
            ->get();

        if ($requests->isEmpty()) {
            $this->info('No detail changes are waiting.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($requests as $request) {
            $label = "#{$request->id} " . ($request->client?->company_name ?? '?') . ' / ' . ($request->product?->name ?? 'deleted product')
                . ' [' . implode(', ', array_keys($request->changes ?? [])) . ']';

            if ($this->option('dry-run')) {
                $this->line($label);

                continue;
            }

            try {
                $reviewer->approveDetails($request);
                $this->line("Applied {$label}");
            } catch (InventoryRequestRefused $e) {
                $failed++;
                $this->warn("Skipped {$label}: {$e->getMessage()}");
            }
        }

        $this->info($this->option('dry-run')
            ? "{$requests->count()} waiting. Run without --dry-run to apply them."
            : ($requests->count() - $failed) . " applied, {$failed} skipped.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

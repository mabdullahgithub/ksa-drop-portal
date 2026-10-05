<?php

namespace App\Services\Inventory;

use App\Models\StockScan;
use RuntimeException;

/**
 * A scan the parcel's current state doesn't allow. `reason` is a stable code
 * the app translates; `last` is the scan that got there first, when one did.
 */
class StockScanRefused extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?StockScan $last = null,
    ) {
        parent::__construct($message);
    }
}

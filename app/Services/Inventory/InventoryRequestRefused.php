<?php

namespace App\Services\Inventory;

use RuntimeException;

/**
 * A decision the request's current state doesn't allow. `reason` is a stable
 * code; `status` is the HTTP status to answer with: 409 when someone else got
 * there first, 422 when the decision itself can't be carried out.
 */
class InventoryRequestRefused extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 409,
    ) {
        parent::__construct($message);
    }
}

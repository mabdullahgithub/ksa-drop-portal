<?php

namespace App\Services\Shipping;

use RuntimeException;

/**
 * A rider asked for an update the parcel's current state doesn't allow.
 * `reason` is a stable code the rider app translates.
 */
class RiderActionRefused extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?string $heldBy = null,
    ) {
        parent::__construct($message);
    }
}

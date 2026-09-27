<?php

namespace App\Services\Riders;

use RuntimeException;

/**
 * Sign-in didn't go through. `reason` is a stable code the rider app
 * translates: link_invalid, link_used, link_replaced, link_expired,
 * invalid_credentials, pin_locked, suspended.
 */
class RiderSignInRefused extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}

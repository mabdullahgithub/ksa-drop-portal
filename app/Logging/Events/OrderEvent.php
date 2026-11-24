<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\EventSpec;

/**
 * Order events, IDs 20001-20999.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum OrderEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    public function spec(): EventSpec
    {
        return match ($this) {
        };
    }
}

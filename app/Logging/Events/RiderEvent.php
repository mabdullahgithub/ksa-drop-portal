<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * Rider events, IDs 34001-34999.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum RiderEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    case SIGNED_IN = 34001;
    case SIGNED_OUT = 34002;
    case SIGN_IN_REFUSED = 34601;

    public function spec(): EventSpec
    {
        $history = Retention::HISTORY;

        return match ($this) {
            self::SIGNED_IN => EventSpec::info('Rider signed in to the rider app (src = link or pin)', required: [Ctx::RIDER, Ctx::SOURCE], keep: $history),
            self::SIGNED_OUT => EventSpec::info('Rider signed out of the rider app on this phone', required: [Ctx::RIDER], keep: $history),
            self::SIGN_IN_REFUSED => EventSpec::warning('Rider sign-in refused (src = link or pin, vcd = refusal code)', required: [Ctx::SOURCE, Ctx::VENDOR_CODE], keep: $history),
        };
    }
}

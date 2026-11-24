<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * Settings events, IDs 60001-60999: settings and connectors. Log setting names, never values.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum SettingsEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    case CONNECTOR_SECRET_REVEALED = 60001;


    public function spec(): EventSpec
    {
        $history = Retention::HISTORY;

        return match ($this) {
            self::CONNECTOR_SECRET_REVEALED => EventSpec::notice('A connector secret was revealed on screen (stk = setting name)', required: [Ctx::CONNECTOR, Ctx::SETTING_KEY], keep: $history),
        };
    }
}

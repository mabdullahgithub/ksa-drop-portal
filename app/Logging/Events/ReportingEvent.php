<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * Reporting events, IDs 52001-52999: Slack ops notifications and scheduled reports.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum ReportingEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    case SLACK_POST_FAILED = 52801;


    public function spec(): EventSpec
    {
        $history = Retention::HISTORY;

        return match ($this) {
            self::SLACK_POST_FAILED => EventSpec::error('Daily stock report could not be posted to Slack; Slack\'s answer is in the payloads log', required: [Ctx::HTTP_STATUS]),
        };
    }
}

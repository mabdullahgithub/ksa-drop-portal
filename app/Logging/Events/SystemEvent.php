<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\EventSpec;

/**
 * System events, IDs 10001-10999: exceptions, boot, scheduler, queue and the
 * logging system itself.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures (900+ is the logging system).
 */
enum SystemEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    case EXCEPTION_REPORTED = 10001;
    case UNCODED_RECORD = 10002;
    case EXCEPTION_DETAIL = 10003;
    case SERVER_ERROR_PAGE_SERVED = 10004;
    case INVALID_CONTEXT = 10901;

    public function spec(): EventSpec
    {
        return match ($this) {
            self::EXCEPTION_REPORTED => EventSpec::error(
                'Unhandled exception reported; full detail is in the exceptions log under the same fingerprint',
                required: [Ctx::EXCEPTION_FINGERPRINT],
            ),
            self::UNCODED_RECORD => EventSpec::warning(
                'A framework or library wrote a log record that has no event ID',
                optional: [Ctx::EXCEPTION_FINGERPRINT],
            ),
            self::EXCEPTION_DETAIL => EventSpec::info(
                'Exception detail (class, message, location, trace), written once per fingerprint per day',
                required: [Ctx::EXCEPTION_FINGERPRINT],
                channel: 'exceptions',
            ),
            self::SERVER_ERROR_PAGE_SERVED => EventSpec::error(
                'An HTTP error page was served to a browser for a server error that was not otherwise reported',
                required: [Ctx::HTTP_STATUS],
                optional: [Ctx::EXCEPTION_FINGERPRINT],
            ),
            self::INVALID_CONTEXT => EventSpec::error(
                'An event was emitted with invalid context (unknown key, wrong type or missing key) and the bad keys were dropped',
                required: [Ctx::SUBJECT_EVENT],
                optional: [Ctx::COUNT],
            ),
        };
    }
}

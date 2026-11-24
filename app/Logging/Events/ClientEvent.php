<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * Client events, IDs 13001-13999: merchants and the client portal.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum ClientEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    case WELCOME_MAIL_FAILED = 13801;
    case CREATED_NOTIFICATION_FAILED = 13802;
    case PASSWORD_CHANGED_MAIL_FAILED = 13803;


    public function spec(): EventSpec
    {
        $history = Retention::HISTORY;

        return match ($this) {
            self::WELCOME_MAIL_FAILED => EventSpec::error('Welcome email to the new client failed', required: [Ctx::CLIENT]),
            self::CREATED_NOTIFICATION_FAILED => EventSpec::error('Notifying admins about the new client failed', required: [Ctx::CLIENT]),
            self::PASSWORD_CHANGED_MAIL_FAILED => EventSpec::error('Password-changed email to the client failed', required: [Ctx::CLIENT]),
        };
    }
}

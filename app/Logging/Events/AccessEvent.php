<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * Access events, IDs 12001-12999: roles, permissions, teams, client access, PIN-locked screens.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum AccessEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    case ROLE_ATTACHED = 12001;
    case ROLE_DETACHED = 12002;
    case PERMISSION_ATTACHED = 12003;
    case PERMISSION_DETACHED = 12004;
    case IMPERSONATION_STARTED = 12005;
    case IMPERSONATION_ENDED = 12006;
    case SCREEN_UNLOCK_FAILED = 12601;


    public function spec(): EventSpec
    {
        $history = Retention::HISTORY;

        return match ($this) {
            self::ROLE_ATTACHED => EventSpec::notice('Role given (mdl/rid = who received it, usually a user)', required: [Ctx::MODEL_TYPE, Ctx::RECORD, Ctx::ROLE], keep: $history),
            self::ROLE_DETACHED => EventSpec::notice('Role taken away (mdl/rid = from whom)', required: [Ctx::MODEL_TYPE, Ctx::RECORD, Ctx::ROLE], keep: $history),
            self::PERMISSION_ATTACHED => EventSpec::notice('Permission granted (mdl/rid = to which role or user)', required: [Ctx::MODEL_TYPE, Ctx::RECORD, Ctx::PERMISSION], keep: $history),
            self::PERMISSION_DETACHED => EventSpec::notice('Permission revoked (mdl/rid = from which role or user)', required: [Ctx::MODEL_TYPE, Ctx::RECORD, Ctx::PERMISSION], keep: $history),
            self::IMPERSONATION_STARTED => EventSpec::notice('Staff member started viewing the portal as a client', required: [Ctx::CLIENT], keep: $history),
            self::IMPERSONATION_ENDED => EventSpec::info('Staff member stopped viewing the portal as a client', required: [Ctx::CLIENT], keep: $history),
            self::SCREEN_UNLOCK_FAILED => EventSpec::warning('Wrong PIN for a locked screen (src = recycle_bin, requests or whatsapp)', required: [Ctx::SOURCE, Ctx::REASON], keep: $history),
        };
    }
}

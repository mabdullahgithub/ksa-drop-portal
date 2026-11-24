<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * Audit events, IDs 61001-61999: the lifecycle of every business record
 * (ModelHistoryObserver, for the models listed in ModelType). A query-builder
 * write bypasses model events, so code doing one emits RECORD_UPDATED per row. `mdl` names the table, `rid` the row, `fld` the
 * columns that changed (names only, never values). The actor is the line's
 * `at`/`a`.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum AuditEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    case RECORD_CREATED = 61001;
    case RECORD_UPDATED = 61002;
    case RECORD_DELETED = 61003;
    case RECORD_RESTORED = 61004;
    case RECORD_PURGED = 61005;

    public function spec(): EventSpec
    {
        $history = Retention::HISTORY;

        return match ($this) {
            self::RECORD_CREATED => EventSpec::info('Record created', required: [Ctx::MODEL_TYPE, Ctx::RECORD], optional: [Ctx::CLIENT], keep: $history),
            self::RECORD_UPDATED => EventSpec::info('Record changed (fld = columns that changed)', required: [Ctx::MODEL_TYPE, Ctx::RECORD, Ctx::FIELDS], optional: [Ctx::CLIENT], keep: $history),
            self::RECORD_DELETED => EventSpec::notice('Record deleted (soft-deleted records can be restored from the recycle bin)', required: [Ctx::MODEL_TYPE, Ctx::RECORD], optional: [Ctx::CLIENT], keep: $history),
            self::RECORD_RESTORED => EventSpec::notice('Record restored from the recycle bin', required: [Ctx::MODEL_TYPE, Ctx::RECORD], optional: [Ctx::CLIENT], keep: $history),
            self::RECORD_PURGED => EventSpec::warning('Record permanently deleted', required: [Ctx::MODEL_TYPE, Ctx::RECORD], optional: [Ctx::CLIENT], keep: $history),
        };
    }
}

<?php

namespace App\Logging\Enums;

/**
 * How long an event's lines are kept, which decides the file it goes to.
 *
 * PRUNABLE: operational/diagnostic lines (API calls, webhook receipts,
 *   refusals, retries, failures). Deleted after LOG_PRUNABLE_DAYS (45).
 * HISTORY: a business fact someone may need to look back on (who changed
 *   what, money, access, compliance). Written to storage/logs/history and never
 *   pruned unless LOG_HISTORY_DAYS is set. Lines are IDs only, so keeping them
 *   long keeps no personal data.
 */
enum Retention: int
{
    case PRUNABLE = 1;
    case HISTORY = 2;

    public function description(): string
    {
        return match ($this) {
            self::PRUNABLE => 'Operational; deleted after the prunable window (default 45 days)',
            self::HISTORY => 'Business history; kept in storage/logs/history, not pruned by default',
        };
    }

    /** Days to keep; 0 means forever. */
    public function days(): int
    {
        return match ($this) {
            self::PRUNABLE => (int) config('logging.retention.prunable_days', 45),
            self::HISTORY => (int) config('logging.retention.history_days', 0),
        };
    }
}

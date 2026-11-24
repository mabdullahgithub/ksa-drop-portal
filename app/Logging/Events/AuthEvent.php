<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * Auth events, IDs 11001-11999: sign-in, password reset, 2FA, impersonation, screen lock.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum AuthEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    case PASSWORD_RESET_LINK_SENT = 11001;
    case SIGNED_IN = 11003;
    case SIGNED_OUT = 11004;
    case PASSWORD_RESET_COMPLETED = 11005;
    case EMAIL_VERIFIED = 11006;
    case REGISTERED = 11007;
    case SIGN_IN_FAILED = 11603;
    case SIGN_IN_LOCKED_OUT = 11604;
    case PASSWORD_RESET_MAIL_QUEUED = 11002;
    case PASSWORD_RESET_LINK_NOT_SENT = 11601;
    case PASSWORD_RESET_SKIPPED_EMAIL_DISABLED = 11602;
    case PASSWORD_RESET_LINK_FAILED = 11801;


    public function spec(): EventSpec
    {
        $history = Retention::HISTORY;

        return match ($this) {
            self::SIGNED_IN => EventSpec::info('User signed in to the portal', required: [Ctx::TARGET_USER], keep: $history),
            self::SIGNED_OUT => EventSpec::info('User signed out', required: [Ctx::TARGET_USER], keep: $history),
            self::PASSWORD_RESET_COMPLETED => EventSpec::notice('User set a new password through a reset link', required: [Ctx::TARGET_USER], keep: $history),
            self::EMAIL_VERIFIED => EventSpec::info('User verified their email address', required: [Ctx::TARGET_USER], keep: $history),
            self::REGISTERED => EventSpec::info('User registered', required: [Ctx::TARGET_USER], keep: $history),
            self::SIGN_IN_FAILED => EventSpec::warning('Sign-in failed (usr is set when the account exists)', required: [Ctx::REASON], optional: [Ctx::TARGET_USER], keep: $history),
            self::SIGN_IN_LOCKED_OUT => EventSpec::warning('Sign-in locked out after too many attempts', required: [Ctx::REASON], keep: $history),
            self::PASSWORD_RESET_LINK_SENT => EventSpec::info('Password reset link sent to the user', required: [Ctx::TARGET_USER], optional: [Ctx::CLIENT], keep: $history),
            self::PASSWORD_RESET_MAIL_QUEUED => EventSpec::info('Password reset email queued for the user (reaches the transport when a queue worker runs)', required: [Ctx::TARGET_USER]),
            self::PASSWORD_RESET_LINK_NOT_SENT => EventSpec::warning('Password reset link not sent (vcd = broker status, e.g. passwords.throttled)', required: [Ctx::TARGET_USER], optional: [Ctx::CLIENT, Ctx::VENDOR_CODE]),
            self::PASSWORD_RESET_SKIPPED_EMAIL_DISABLED => EventSpec::warning('Password reset email skipped: email is switched off', required: [Ctx::TARGET_USER]),
            self::PASSWORD_RESET_LINK_FAILED => EventSpec::error('Sending the password reset link failed', required: [Ctx::TARGET_USER], optional: [Ctx::CLIENT]),
        };
    }
}

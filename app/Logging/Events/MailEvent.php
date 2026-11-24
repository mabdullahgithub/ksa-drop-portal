<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * Mail events, IDs 51001-51999: outbound email, bounces, the graveyard and the
 * email settings. Never log an address or a subject; email_graveyard and
 * email_logs rows hold them.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum MailEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    // History
    case MESSAGE_SENT = 51001;
    case GRAVEYARD_ADDRESS_ADDED = 51002;
    case GRAVEYARD_ADDRESS_RESTORED = 51003;
    case SETTINGS_UPDATED = 51004;

    // Outbound
    case MESSAGE_SENDING = 51401;
    case TEST_CONNECTION_ATTEMPTED = 51402;

    // Refusals
    case GRAVEYARD_RECIPIENTS_BLOCKED = 51601;
    case BOUNCE_IGNORED_UNCONFIRMED = 51602;
    case RATE_LIMITED = 51603;

    // Failures
    case TRANSPORT_FAILED = 51801;
    case BOUNCE_CHECK_FAILED = 51802;
    case GRAVEYARD_CHECK_FAILED = 51803;
    case PASSWORD_DECRYPT_FAILED = 51804;
    case SAVED_SETTINGS_NOT_APPLIED = 51805;
    case REJECTED_RECIPIENT_NOT_RECORDED = 51806;

    public function spec(): EventSpec
    {
        $history = Retention::HISTORY;

        return match ($this) {
            self::MESSAGE_SENT => EventSpec::info('Email handed to the mail transport (count = recipients, mtp = mailable)', required: [Ctx::COUNT], optional: [Ctx::MAIL_TYPE], keep: $history),
            self::GRAVEYARD_ADDRESS_ADDED => EventSpec::warning('Address added to the email graveyard; no more mail goes to it (src = smtp or bounce, vcd = status code)', required: [Ctx::GRAVEYARD_ENTRY], optional: [Ctx::SOURCE, Ctx::VENDOR_CODE], keep: $history),
            self::GRAVEYARD_ADDRESS_RESTORED => EventSpec::notice('Address taken out of the email graveyard; mail reaches it again', required: [Ctx::GRAVEYARD_ENTRY], keep: $history),
            self::SETTINGS_UPDATED => EventSpec::notice('Email settings changed (vcd = driver)', required: [Ctx::EMAIL_SETTING], optional: [Ctx::ENABLED, Ctx::VENDOR_CODE], keep: $history),

            self::MESSAGE_SENDING => EventSpec::info('Email on its way to the transport (count = recipients, mtp = mailable)', required: [Ctx::COUNT], optional: [Ctx::MAIL_TYPE]),
            self::TEST_CONNECTION_ATTEMPTED => EventSpec::info('Email settings test connection attempted', required: [Ctx::SUCCEEDED]),

            self::GRAVEYARD_RECIPIENTS_BLOCKED => EventSpec::info('Recipients removed from an outgoing email because they are in the graveyard', required: [Ctx::COUNT]),
            self::BOUNCE_IGNORED_UNCONFIRMED => EventSpec::warning('Bounce report ignored: it does not quote a message token of ours (vcd = mailbox UID)', optional: [Ctx::VENDOR_CODE]),
            self::RATE_LIMITED => EventSpec::warning('Email to a user skipped: 20 per user per hour reached (mtp = email type)', required: [Ctx::TARGET_USER], optional: [Ctx::MAIL_TYPE]),

            self::TRANSPORT_FAILED => EventSpec::error('Mail transport failed (SMTP auth, TLS, host unreachable or message rejected); mailer settings and cause chain are in the exceptions log'),
            self::BOUNCE_CHECK_FAILED => EventSpec::warning('Checking the bounce mailbox failed'),
            self::GRAVEYARD_CHECK_FAILED => EventSpec::warning('Graveyard check failed; the email was sent anyway'),
            self::PASSWORD_DECRYPT_FAILED => EventSpec::error('Saved email password could not be decrypted (APP_KEY changed?)', required: [Ctx::EMAIL_SETTING]),
            self::SAVED_SETTINGS_NOT_APPLIED => EventSpec::warning('Saved email settings could not be applied; the .env mailer is used'),
            self::REJECTED_RECIPIENT_NOT_RECORDED => EventSpec::warning('A recipient the mail server refused could not be added to the graveyard'),
        };
    }
}

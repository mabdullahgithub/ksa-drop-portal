<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * WhatsApp events, IDs 50001-50999: outbound sends (channel `whatsapp`) and the
 * Meta webhook (channel `whatsapp_webhooks`). Never log a phone number or a
 * message body; the whatsapp_messages row holds them.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum WhatsAppEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    private const WEBHOOKS = 'whatsapp_webhooks';

    // History
    case MESSAGE_SENT = 50001;
    case AGENT_REPLY_SENT = 50002;
    case INBOUND_MESSAGE_RECEIVED = 50003;
    case REPLY_CLASSIFIED = 50004;
    case MESSAGING_SWITCHED = 50005;
    case ORDER_MOVED_TO_GRAVEYARD = 50006;
    case MESSAGE_STATUS_UPDATED = 50007;

    // Inbound
    case WEBHOOK_VERIFIED = 50201;
    case INBOUND_UNMATCHED = 50202;

    // Refusals
    case VERIFY_TOKEN_NOT_CONFIGURED = 50601;
    case VERIFY_TOKEN_MISMATCH = 50602;
    case APP_SECRET_NOT_CONFIGURED = 50603;
    case SEND_SKIPPED_DISABLED = 50604;
    case SEND_SKIPPED_NO_PHONE = 50605;
    case FOLLOWUP_SKIPPED_UNDELIVERABLE = 50606;

    // Failures
    case SEND_FAILED = 50801;
    case AGENT_REPLY_FAILED = 50802;

    public function spec(): EventSpec
    {
        $history = Retention::HISTORY;

        return match ($this) {
            self::MESSAGE_SENT => EventSpec::info('WhatsApp template message sent to the customer (vcd = template key)', required: [Ctx::ORDER], optional: [Ctx::VENDOR_CODE], keep: $history),
            self::AGENT_REPLY_SENT => EventSpec::info('Agent replied to the customer over WhatsApp', required: [Ctx::ORDER, Ctx::WHATSAPP_MESSAGE], keep: $history),
            self::INBOUND_MESSAGE_RECEIVED => EventSpec::info('Customer WhatsApp message received and matched to the order (vcd = message type)', required: [Ctx::ORDER, Ctx::WHATSAPP_MESSAGE], optional: [Ctx::VENDOR_CODE], keep: $history),
            self::REPLY_CLASSIFIED => EventSpec::info('Customer reply classified and applied to the order (vcd = intent: confirm, update_address, cancel, unknown)', required: [Ctx::ORDER, Ctx::VENDOR_CODE], keep: $history),
            self::MESSAGING_SWITCHED => EventSpec::notice('WhatsApp messaging switched on or off', required: [Ctx::ENABLED], keep: $history),
            self::ORDER_MOVED_TO_GRAVEYARD => EventSpec::info('Order moved to the WhatsApp graveyard after the follow-up went unanswered', required: [Ctx::ORDER], keep: $history),
            self::MESSAGE_STATUS_UPDATED => EventSpec::info('WhatsApp message delivery status changed (vcd = sent, delivered, read or failed)', required: [Ctx::WHATSAPP_MESSAGE, Ctx::VENDOR_CODE], optional: [Ctx::ORDER], keep: $history),

            self::WEBHOOK_VERIFIED => EventSpec::info('Meta webhook subscription verified', channel: self::WEBHOOKS),
            self::INBOUND_UNMATCHED => EventSpec::info('Inbound WhatsApp message matched no order', channel: self::WEBHOOKS),

            self::VERIFY_TOKEN_NOT_CONFIGURED => EventSpec::error('Meta webhook verification rejected: no verify token configured', channel: self::WEBHOOKS),
            self::VERIFY_TOKEN_MISMATCH => EventSpec::warning('Meta webhook verification rejected: verify token mismatch', channel: self::WEBHOOKS),
            self::APP_SECRET_NOT_CONFIGURED => EventSpec::error('Meta webhook rejected: app secret not configured, signature cannot be checked', channel: self::WEBHOOKS),
            self::SEND_SKIPPED_DISABLED => EventSpec::info('WhatsApp send skipped: messaging is switched off (vcd = template key)', required: [Ctx::ORDER], optional: [Ctx::VENDOR_CODE]),
            self::SEND_SKIPPED_NO_PHONE => EventSpec::warning('WhatsApp send skipped: the order has no usable phone number; order marked failed', required: [Ctx::ORDER, Ctx::REASON]),
            self::FOLLOWUP_SKIPPED_UNDELIVERABLE => EventSpec::info('WhatsApp follow-up skipped: the first message was undeliverable', required: [Ctx::ORDER]),

            self::SEND_FAILED => EventSpec::error('WhatsApp send failed (vcd = template key); retried until attempts run out', required: [Ctx::ORDER], optional: [Ctx::VENDOR_CODE, Ctx::ATTEMPT]),
            self::AGENT_REPLY_FAILED => EventSpec::error('Agent WhatsApp reply could not be sent', required: [Ctx::ORDER]),
        };
    }
}

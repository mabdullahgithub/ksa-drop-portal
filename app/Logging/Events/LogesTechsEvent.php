<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * LogesTechs (Navix) events, IDs 33001-33999: the driver (outbound API, channel
 * `logestechs`) and the status webhook (channel `logestechs_webhooks`). Both
 * channels write nothing unless LOGESTECHS_LOGS_ENABLED=true; HISTORY events
 * always reach the history log. `vcd` carries LogesTechs' raw status code.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum LogesTechsEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    private const WEBHOOKS = 'logestechs_webhooks';

    // Inbound
    case TRACKING_PROCESSED = 33201;
    case TRACKING_UNKNOWN_SHIPMENT = 33202;

    // Outbound
    case API_CALLED = 33401;

    // Refusals
    case WEBHOOK_BODY_INVALID = 33601;
    case WEBHOOK_FIELD_MISSING = 33602;
    case WEBHOOK_STATUS_MISSING = 33603;
    case WEBHOOK_CREDENTIALS_NOT_CONFIGURED = 33604;
    case WEBHOOK_CREDENTIALS_MISMATCH = 33605;

    // Failures
    case WEBHOOK_PROCESSING_FAILED = 33801;
    case SHIPMENT_CREATE_FAILED = 33802;
    case LABEL_FAILED = 33803;
    case API_RATE_LIMITED = 33804;
    case API_SERVER_ERROR = 33805;
    case API_ATTEMPT_FAILED = 33806;
    case API_GAVE_UP = 33807;
    case VILLAGE_LOOKUP_FAILED = 33808;

    public function spec(): EventSpec
    {
        return match ($this) {
            self::TRACKING_PROCESSED => EventSpec::info('LogesTechs status push processed; shipment moved to the mapped status', required: [Ctx::SHIPMENT, Ctx::SHIPMENT_STATUS], optional: [Ctx::VENDOR_CODE], keep: Retention::HISTORY),
            self::TRACKING_UNKNOWN_SHIPMENT => EventSpec::warning('LogesTechs status push for a package we do not have (usually a mismatched supplierInvoice); acknowledged', optional: [Ctx::TRACKING_NO, Ctx::EXTERNAL_ORDER_ID, Ctx::VENDOR_CODE], channel: self::WEBHOOKS),

            self::API_CALLED => EventSpec::info('LogesTechs API answered', required: [Ctx::ENDPOINT, Ctx::HTTP_STATUS, Ctx::ATTEMPT]),

            self::WEBHOOK_BODY_INVALID => EventSpec::warning('LogesTechs webhook rejected: body empty or not JSON', channel: self::WEBHOOKS),
            self::WEBHOOK_FIELD_MISSING => EventSpec::warning('LogesTechs webhook rejected: barcode and supplierInvoice both missing', channel: self::WEBHOOKS),
            self::WEBHOOK_STATUS_MISSING => EventSpec::warning('LogesTechs webhook rejected: no newStatus', optional: [Ctx::TRACKING_NO], channel: self::WEBHOOKS),
            self::WEBHOOK_CREDENTIALS_NOT_CONFIGURED => EventSpec::error('LogesTechs webhook rejected: webhook_username/webhook_password are not configured (Apps → LogesTechs Settings)', channel: self::WEBHOOKS),
            self::WEBHOOK_CREDENTIALS_MISMATCH => EventSpec::warning('LogesTechs webhook rejected: username/password do not match our settings', optional: [Ctx::EXTERNAL_ACCOUNT], channel: self::WEBHOOKS),

            self::WEBHOOK_PROCESSING_FAILED => EventSpec::error('LogesTechs status push failed while being processed; LogesTechs will NOT retry it — replay by hand or check Get Package Status', optional: [Ctx::TRACKING_NO, Ctx::EXTERNAL_ORDER_ID, Ctx::VENDOR_CODE], channel: self::WEBHOOKS),
            self::SHIPMENT_CREATE_FAILED => EventSpec::error('LogesTechs refused to create the shipment', required: [Ctx::EXTERNAL_ORDER_ID]),
            self::LABEL_FAILED => EventSpec::warning('LogesTechs label generation failed', required: [Ctx::COUNT]),
            self::API_RATE_LIMITED => EventSpec::warning('LogesTechs API call skipped: our rate limit (30/min) is reached'),
            self::API_SERVER_ERROR => EventSpec::warning('LogesTechs answered with a server error; the request is in the payloads log', required: [Ctx::ENDPOINT, Ctx::HTTP_STATUS]),
            self::API_ATTEMPT_FAILED => EventSpec::warning('LogesTechs API attempt failed; will retry', required: [Ctx::ENDPOINT, Ctx::ATTEMPT]),
            self::API_GAVE_UP => EventSpec::error('LogesTechs API failed on every attempt; gave up', required: [Ctx::ENDPOINT, Ctx::ATTEMPT]),
            self::VILLAGE_LOOKUP_FAILED => EventSpec::error('LogesTechs village/district lookup failed'),
        };
    }
}

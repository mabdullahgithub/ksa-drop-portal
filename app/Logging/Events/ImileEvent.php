<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * iMile events, IDs 32001-32999: the driver (outbound API) and the tracking
 * webhook.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum ImileEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    // Inbound
    case TRACKING_PROCESSED = 32201;
    case TRACKING_UNKNOWN_SHIPMENT = 32202;
    case TRACKING_NO_SCANS = 32203;

    // Outbound
    case API_CALLED = 32401;

    // Refusals
    case WEBHOOK_ENVELOPE_INCOMPLETE = 32601;
    case WEBHOOK_FIELD_MISSING = 32602;
    case WEBHOOK_PARTNER_CODE_MISMATCH = 32603;

    // Failures
    case WEBHOOK_PROCESSING_FAILED = 32801;
    case SHIPMENT_CREATE_FAILED = 32802;
    case TOKEN_REQUEST_FAILED = 32803;
    case TOKEN_REJECTED = 32804;
    case API_RATE_LIMITED = 32805;
    case API_ATTEMPT_FAILED = 32806;
    case API_GAVE_UP = 32807;

    public function spec(): EventSpec
    {
        return match ($this) {
            self::TRACKING_PROCESSED => EventSpec::info('iMile tracking webhook processed; shipment moved to the latest scanned status', required: [Ctx::SHIPMENT, Ctx::SHIPMENT_STATUS, Ctx::COUNT], keep: Retention::HISTORY),
            self::TRACKING_UNKNOWN_SHIPMENT => EventSpec::info('iMile tracking webhook for a waybill we do not have; acknowledged so iMile stops retrying', optional: [Ctx::TRACKING_NO, Ctx::EXTERNAL_ORDER_ID]),
            self::TRACKING_NO_SCANS => EventSpec::info('iMile tracking webhook carried no locus details', required: [Ctx::SHIPMENT]),

            self::API_CALLED => EventSpec::info('iMile API answered', required: [Ctx::ENDPOINT, Ctx::HTTP_STATUS, Ctx::ATTEMPT]),

            self::WEBHOOK_ENVELOPE_INCOMPLETE => EventSpec::warning('iMile webhook rejected: partnerCode, param or timestamp missing'),
            self::WEBHOOK_FIELD_MISSING => EventSpec::warning('iMile webhook rejected: billNo and orderNo both missing'),
            self::WEBHOOK_PARTNER_CODE_MISMATCH => EventSpec::warning('iMile webhook rejected: partnerCode is not one we accept', optional: [Ctx::EXTERNAL_ACCOUNT]),

            self::WEBHOOK_PROCESSING_FAILED => EventSpec::error('iMile tracking webhook failed while being processed', optional: [Ctx::TRACKING_NO]),
            self::SHIPMENT_CREATE_FAILED => EventSpec::error('iMile refused to create the shipment', required: [Ctx::EXTERNAL_ORDER_ID], optional: [Ctx::VENDOR_CODE]),
            self::TOKEN_REQUEST_FAILED => EventSpec::error('iMile access-token request could not be sent'),
            self::TOKEN_REJECTED => EventSpec::error('iMile refused to grant an access token', optional: [Ctx::VENDOR_CODE, Ctx::EXTERNAL_ACCOUNT]),
            self::API_RATE_LIMITED => EventSpec::warning('iMile API call skipped: our rate limit (30/min) is reached'),
            self::API_ATTEMPT_FAILED => EventSpec::warning('iMile API attempt failed; will retry', required: [Ctx::ENDPOINT, Ctx::ATTEMPT]),
            self::API_GAVE_UP => EventSpec::error('iMile API failed on every attempt; gave up', required: [Ctx::ENDPOINT, Ctx::ATTEMPT]),
        };
    }
}

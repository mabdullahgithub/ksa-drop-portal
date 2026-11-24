<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * J&T Express events, IDs 31001-31999: the driver (outbound API) and the
 * tracking/return/COD/OTP webhooks. `tpc` is the webhook kind: tracking,
 * return, cod or otp.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum JntEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    // Inbound
    case TRACKING_PROCESSED = 31201;
    case TRACKING_UNKNOWN_SHIPMENT = 31202;
    case TRACKING_NO_SCANS = 31203;
    case RETURN_PROCESSED = 31211;
    case RETURN_UNKNOWN_SHIPMENT = 31212;
    case COD_PROCESSED = 31221;
    case COD_UNKNOWN_SHIPMENT = 31222;
    case OTP_PROCESSED = 31231;
    case OTP_UNKNOWN_SHIPMENT = 31232;
    case COD_ORDER_PAID = 31223;

    // Outbound
    case COD_DECISION = 31401;
    case API_CALLED = 31402;
    case API_REQUEST_SENT = 31403;
    case LABEL_ENDPOINT_FAILED = 31404;
    case LABEL_FALLBACK_GENERATED = 31405;

    // Refusals
    case WEBHOOK_FIELD_MISSING = 31601;
    case WEBHOOK_SIGNATURE_INVALID = 31602;
    case WEBHOOK_BIZCONTENT_INVALID = 31603;
    case WEBHOOK_DIGEST_MISSING = 31604;
    case WEBHOOK_PRIVATE_KEY_MISSING = 31605;
    case WEBHOOK_SIGNATURE_BYPASSED = 31606;
    case WEBHOOK_ACCOUNT_MISMATCH = 31607;
    case WEBHOOK_SIGNATURE_MISMATCH = 31608;
    case COD_SHIPMENT_WITHOUT_ORDER = 31609;

    // Failures
    case WEBHOOK_PROCESSING_FAILED = 31801;
    case WEBHOOK_PARSE_FAILED = 31802;
    case COD_ORDER_UPDATE_FAILED = 31803;
    case SHIPMENT_CREATE_FAILED = 31804;
    case API_RATE_LIMITED = 31805;
    case API_ATTEMPT_FAILED = 31806;
    case API_GAVE_UP = 31807;

    public function spec(): EventSpec
    {
        return match ($this) {
            self::TRACKING_PROCESSED => EventSpec::info('J&T tracking webhook processed; shipment moved to the latest scanned status', required: [Ctx::SHIPMENT, Ctx::SHIPMENT_STATUS, Ctx::COUNT], keep: Retention::HISTORY),
            self::TRACKING_UNKNOWN_SHIPMENT => EventSpec::info('J&T tracking webhook for a waybill we do not have; acknowledged so J&T stops retrying', optional: [Ctx::TRACKING_NO, Ctx::EXTERNAL_ORDER_ID]),
            self::TRACKING_NO_SCANS => EventSpec::info('J&T tracking webhook carried no scan details', required: [Ctx::SHIPMENT]),
            self::RETURN_PROCESSED => EventSpec::info('J&T return webhook processed; shipment marked returned', required: [Ctx::SHIPMENT], keep: Retention::HISTORY),
            self::RETURN_UNKNOWN_SHIPMENT => EventSpec::info('J&T return webhook for a waybill we do not have', optional: [Ctx::TRACKING_NO, Ctx::EXTERNAL_ORDER_ID]),
            self::COD_PROCESSED => EventSpec::info('J&T COD remittance webhook processed (count = orders marked paid)', required: [Ctx::COUNT, Ctx::FAILED_COUNT], optional: [Ctx::VENDOR_BATCH, Ctx::AMOUNT_HALALAS]),
            self::COD_UNKNOWN_SHIPMENT => EventSpec::info('J&T COD remittance lists a waybill we do not have', required: [Ctx::TRACKING_NO], optional: [Ctx::VENDOR_BATCH]),
            self::COD_ORDER_PAID => EventSpec::info('Order marked paid from a J&T COD remittance', required: [Ctx::ORDER, Ctx::SHIPMENT], optional: [Ctx::VENDOR_BATCH], keep: Retention::HISTORY),
            self::OTP_PROCESSED => EventSpec::info('J&T OTP verification webhook processed', required: [Ctx::SHIPMENT], keep: Retention::HISTORY),
            self::OTP_UNKNOWN_SHIPMENT => EventSpec::info('J&T OTP webhook for a waybill we do not have', optional: [Ctx::TRACKING_NO, Ctx::EXTERNAL_ORDER_ID]),

            self::COD_DECISION => EventSpec::debug('J&T order COD decision (amount 0 means no COD was sent)', required: [Ctx::EXTERNAL_ORDER_ID, Ctx::AMOUNT_HALALAS]),
            self::API_CALLED => EventSpec::info('J&T API answered', required: [Ctx::ENDPOINT, Ctx::HTTP_STATUS, Ctx::ATTEMPT]),
            self::API_REQUEST_SENT => EventSpec::debug('J&T API request sent (body only in the payloads log)', required: [Ctx::ENDPOINT, Ctx::ATTEMPT]),
            self::LABEL_ENDPOINT_FAILED => EventSpec::debug('J&T label endpoint refused; trying the next one', required: [Ctx::ENDPOINT], optional: [Ctx::VENDOR_CODE]),
            self::LABEL_FALLBACK_GENERATED => EventSpec::warning('Every J&T label endpoint failed; an HTML fallback label was generated', required: [Ctx::TRACKING_NO]),

            self::WEBHOOK_FIELD_MISSING => EventSpec::warning('J&T webhook rejected: waybill number missing', required: [Ctx::WEBHOOK_TOPIC]),
            self::WEBHOOK_SIGNATURE_INVALID => EventSpec::warning('J&T webhook rejected: signature verification failed', required: [Ctx::WEBHOOK_TOPIC]),
            self::WEBHOOK_BIZCONTENT_INVALID => EventSpec::warning('J&T webhook rejected: bizContent empty or not JSON', required: [Ctx::WEBHOOK_TOPIC]),
            self::WEBHOOK_DIGEST_MISSING => EventSpec::debug('J&T webhook arrived without a digest header'),
            self::WEBHOOK_PRIVATE_KEY_MISSING => EventSpec::warning('J&T webhook cannot be verified: private_key is not configured in connector settings'),
            self::WEBHOOK_SIGNATURE_BYPASSED => EventSpec::notice('J&T webhook signature check bypassed (sandbox mode is on)', required: [Ctx::WEBHOOK_TOPIC]),
            self::WEBHOOK_ACCOUNT_MISMATCH => EventSpec::warning('J&T webhook rejected: apiAccount is not our configured account', required: [Ctx::EXTERNAL_ACCOUNT]),
            self::WEBHOOK_SIGNATURE_MISMATCH => EventSpec::debug('J&T webhook digest did not match the computed signature', required: [Ctx::WEBHOOK_TOPIC]),
            self::COD_SHIPMENT_WITHOUT_ORDER => EventSpec::warning('J&T COD remittance for a shipment whose order no longer exists', required: [Ctx::SHIPMENT], optional: [Ctx::VENDOR_BATCH]),

            self::WEBHOOK_PROCESSING_FAILED => EventSpec::error('J&T webhook failed while being processed', required: [Ctx::WEBHOOK_TOPIC], optional: [Ctx::TRACKING_NO]),
            self::WEBHOOK_PARSE_FAILED => EventSpec::error('J&T webhook body could not be parsed', required: [Ctx::WEBHOOK_TOPIC]),
            self::COD_ORDER_UPDATE_FAILED => EventSpec::error('J&T COD remittance: marking the order paid failed', required: [Ctx::TRACKING_NO], optional: [Ctx::VENDOR_BATCH]),
            self::SHIPMENT_CREATE_FAILED => EventSpec::error('J&T refused to create the shipment', required: [Ctx::EXTERNAL_ORDER_ID], optional: [Ctx::VENDOR_CODE]),
            self::API_RATE_LIMITED => EventSpec::warning('J&T API call skipped: our rate limit (30/min) is reached'),
            self::API_ATTEMPT_FAILED => EventSpec::warning('J&T API attempt failed; will retry', required: [Ctx::ENDPOINT, Ctx::ATTEMPT]),
            self::API_GAVE_UP => EventSpec::error('J&T API failed on every attempt; gave up', required: [Ctx::ENDPOINT, Ctx::ATTEMPT]),
        };
    }
}

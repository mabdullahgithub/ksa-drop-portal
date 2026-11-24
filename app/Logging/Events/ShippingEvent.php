<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * Shipping events, IDs 30001-30999: CourierManager, booking, tracking sync, shipment events.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum ShippingEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    case BOOKED_BUT_NOT_SAVED = 30801;
    case DELIVERED_NOTIFICATION_FAILED = 30802;
    case EXCEPTION_NOTIFICATION_FAILED = 30803;


    public function spec(): EventSpec
    {
        $history = Retention::HISTORY;

        return match ($this) {
            self::BOOKED_BUT_NOT_SAVED => EventSpec::critical('Shipment booked at the courier but not saved here; the waybill must be recorded by hand', required: [Ctx::ORDER, Ctx::TRACKING_NO], optional: [Ctx::COURIER, Ctx::EXTERNAL_ORDER_ID]),
            self::DELIVERED_NOTIFICATION_FAILED => EventSpec::error('Notifying admins that a shipment was delivered failed', required: [Ctx::SHIPMENT]),
            self::EXCEPTION_NOTIFICATION_FAILED => EventSpec::error('Notifying admins about a shipment exception failed', required: [Ctx::SHIPMENT]),
        };
    }
}

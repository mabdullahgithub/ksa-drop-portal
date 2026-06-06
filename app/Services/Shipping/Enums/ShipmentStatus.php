<?php

namespace App\Services\Shipping\Enums;

enum ShipmentStatus: string
{
    case PENDING = 'pending';
    case INFO_RECEIVED = 'info_received';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case ATTEMPT_FAIL = 'attempt_fail';
    case DELIVERED = 'delivered';
    case EXCEPTION = 'exception';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::INFO_RECEIVED => 'Info Received',
            self::IN_TRANSIT => 'In Transit',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::ATTEMPT_FAIL => 'Attempt Failed',
            self::DELIVERED => 'Delivered',
            self::EXCEPTION => 'Exception',
            self::RETURNED => 'Returned',
            self::CANCELLED => 'Cancelled',
            self::FAILED => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::INFO_RECEIVED => 'blue',
            self::IN_TRANSIT => 'indigo',
            self::OUT_FOR_DELIVERY => 'orange',
            self::ATTEMPT_FAIL => 'red',
            self::DELIVERED => 'green',
            self::EXCEPTION => 'red',
            self::RETURNED => 'yellow',
            self::CANCELLED => 'gray',
            self::FAILED => 'red',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::INFO_RECEIVED,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::ATTEMPT_FAIL,
        ]);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::DELIVERED,
            self::RETURNED,
            self::CANCELLED,
            self::FAILED,
        ]);
    }
}

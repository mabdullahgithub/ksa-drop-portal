<?php

namespace App\Services\Shipping\Enums;

/**
 * What a KSA Express rider can do to a parcel after scanning it.
 */
enum RiderAction: string
{
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case ATTEMPT_FAILED = 'attempt_failed';

    public function targetStatus(): ShipmentStatus
    {
        return match ($this) {
            self::OUT_FOR_DELIVERY => ShipmentStatus::OUT_FOR_DELIVERY,
            self::DELIVERED => ShipmentStatus::DELIVERED,
            self::ATTEMPT_FAILED => ShipmentStatus::ATTEMPT_FAIL,
        };
    }

    /**
     * Wording for shipments.tracking_history, which the public /track page
     * shows — keep it free of anything but the rider's first name.
     */
    public function publicDescription(string $riderFirstName, ?FailedAttemptReason $reason = null): string
    {
        return match ($this) {
            self::OUT_FOR_DELIVERY => "Out for delivery with {$riderFirstName}",
            self::DELIVERED => 'Delivered',
            self::ATTEMPT_FAILED => 'Delivery attempt failed' . ($reason ? ' — ' . $reason->label() : ''),
        };
    }
}

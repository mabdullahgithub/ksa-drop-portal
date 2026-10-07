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
    // The delivery is over without the customer taking the parcel. It stays
    // with the rider until they hand it back.
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    // Handed a returned or cancelled parcel back at the hub.
    case RETURNED_TO_HUB = 'returned_to_hub';

    /**
     * Handing a parcel back doesn't change what happened to it: it stays
     * returned or cancelled.
     */
    public function targetStatus(ShipmentStatus $current): ShipmentStatus
    {
        return match ($this) {
            self::OUT_FOR_DELIVERY => ShipmentStatus::OUT_FOR_DELIVERY,
            self::DELIVERED => ShipmentStatus::DELIVERED,
            self::ATTEMPT_FAILED => ShipmentStatus::ATTEMPT_FAIL,
            self::RETURNED => ShipmentStatus::RETURNED,
            self::CANCELLED => ShipmentStatus::CANCELLED,
            self::RETURNED_TO_HUB => $current,
        };
    }

    /**
     * The rider has to say why the customer didn't get the parcel.
     */
    public function needsReason(): bool
    {
        return in_array($this, [self::ATTEMPT_FAILED, self::RETURNED, self::CANCELLED], true);
    }

    /**
     * The rider went and the customer didn't take the parcel. They take a
     * photo of the place to show it — which is what gets the attempt paid
     * (App\Services\Riders\RiderPay). Returning or cancelling the parcel
     * afterwards is a separate step: no photo, no pay.
     */
    public function needsProof(): bool
    {
        return $this === self::ATTEMPT_FAILED;
    }

    /**
     * Wording for shipments.tracking_history, which the public /track page
     * shows — keep it free of anything but the rider's first name.
     */
    public function publicDescription(string $riderFirstName, ?FailedAttemptReason $reason = null): string
    {
        $why = $reason ? ' — ' . $reason->label() : '';

        return match ($this) {
            self::OUT_FOR_DELIVERY => "Out for delivery with {$riderFirstName}",
            self::DELIVERED => 'Delivered',
            self::ATTEMPT_FAILED => 'Delivery attempt failed' . $why,
            self::RETURNED => 'Not delivered, returning to sender' . $why,
            self::CANCELLED => 'Delivery cancelled' . $why,
            self::RETURNED_TO_HUB => 'Returned to KSA Drop',
        };
    }
}

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
            self::EXCEPTION,
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

    /**
     * Relative depth of the non-terminal, non-exception "happy path" states,
     * used only by {@see shouldTransitionTo()} to stop an out-of-order courier
     * event from moving a shipment backward (e.g. iMile's `SCH` — a delivery
     * appointment booked — tends to arrive *after* the parcel has already
     * reached IN_TRANSIT/OUT_FOR_DELIVERY, but normalises to INFO_RECEIVED).
     */
    private const PROGRESSION_RANK = [
        'pending'          => 0,
        'info_received'    => 1,
        'in_transit'       => 2,
        'out_for_delivery' => 3,
        'attempt_fail'     => 3,
    ];

    /**
     * Whether a shipment currently at $this status should move to $incoming.
     *
     * Both J&T and iMile push tracking events whose delivery order isn't
     * guaranteed (retries, batched pulls, out-of-order webhooks), so blindly
     * applying "whatever the latest event says" can visibly regress a
     * shipment's status. The rule:
     *
     *  - A courier's terminal outcome (Delivered/Returned/Cancelled/Failed)
     *    always wins — it's authoritative regardless of ordering.
     *  - Nothing can move a shipment off a terminal outcome once recorded.
     *  - Exception is a flagged-for-attention state, not a dead end: it can
     *    be entered from anywhere, and a later plain progression event is
     *    read as "it recovered" and is allowed to move it forward again —
     *    otherwise a resolved exception would stay stuck on display forever.
     *  - Otherwise, only forward (or lateral, same-rank) moves through the
     *    ordinary PENDING → INFO_RECEIVED → IN_TRANSIT → OUT_FOR_DELIVERY
     *    progression are applied.
     */
    public function shouldTransitionTo(self $incoming): bool
    {
        if ($incoming->isTerminal()) {
            return true;
        }

        if ($this->isTerminal()) {
            return false;
        }

        if ($incoming === self::EXCEPTION || $this === self::EXCEPTION) {
            return true;
        }

        return (self::PROGRESSION_RANK[$incoming->value] ?? 0) >= (self::PROGRESSION_RANK[$this->value] ?? 0);
    }
}

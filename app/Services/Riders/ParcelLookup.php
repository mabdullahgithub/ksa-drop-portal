<?php

namespace App\Services\Riders;

use App\Models\Shipment;
use App\Services\Shipping\Drivers\KsaDropExpressDriver;

/**
 * From a scanned or typed code to the parcel it is on.
 */
class ParcelLookup
{
    /**
     * The label's QR code holds a tracking URL (…/track?q=KSD…); take the
     * number out of it. Hardware scanners may add whitespace.
     */
    public static function normalize(string $raw): string
    {
        $code = trim($raw);

        if (preg_match('#^https?://#i', $code)) {
            parse_str((string) parse_url($code, PHP_URL_QUERY), $query);
            $code = trim((string) ($query['q'] ?? ''));
        }

        return mb_substr($code, 0, 100);
    }

    /**
     * For a rider: the waybill number first, then the order number (the
     * smaller barcode on the label) — KSA Express parcels only.
     */
    public static function forRider(string $code): ?Shipment
    {
        $upper = strtoupper($code);

        $shipment = Shipment::where('courier', KsaDropExpressDriver::KEY)
            ->where('tracking_number', $upper)
            ->first();

        if ($shipment) {
            return $shipment;
        }

        $shipment = Shipment::where('courier', KsaDropExpressDriver::KEY)
            ->whereHas('order', fn ($q) => $q->whereIn('order_number', self::orderNumbers($code)))
            ->latest('id')
            ->first();

        // Another courier's waybill: return it so the app can say so, rather
        // than "not found".
        return $shipment ?? Shipment::where('tracking_number', $code)->first();
    }

    /**
     * For the warehouse: whichever courier carries it, since every parcel
     * that leaves takes stock with it. An order booked more than once is its
     * newest booking.
     */
    public static function anyCourier(string $code): ?Shipment
    {
        $shipment = Shipment::whereIn('tracking_number', array_unique([$code, strtoupper($code)]))
            ->latest('id')
            ->first();

        return $shipment ?? Shipment::whereHas('order', fn ($q) => $q->whereIn('order_number', self::orderNumbers($code)))
            ->latest('id')
            ->first();
    }

    /**
     * @return list<string> the number as stored with and without its "#"
     */
    private static function orderNumbers(string $code): array
    {
        $orderNumber = ltrim($code, '#');

        return [$orderNumber, '#' . $orderNumber];
    }
}

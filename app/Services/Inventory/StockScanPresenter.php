<?php

namespace App\Services\Inventory;

use App\Models\Shipment;
use App\Models\StockScan;
use App\Models\StockScanItem;

/**
 * A stock scan, and the parcel it was made on, as the inventory manager's
 * app and the portal's scan log show them.
 */
class StockScanPresenter
{
    /** Lists should eager-load these. */
    public const RELATIONS = ['shipment.order', 'items', 'rider:id,name', 'parcelRider:id,name'];

    public static function scan(StockScan $scan): array
    {
        $scan->loadMissing(self::RELATIONS);

        return [
            'id' => $scan->id,
            'direction' => $scan->direction,
            'occurred_at' => $scan->occurred_at->toIso8601String(),
            'entry_method' => $scan->entry_method,
            'pieces' => $scan->pieces,
            // Items that matched no product, so no stock changed for them.
            'unmatched' => $scan->items->reject->isMatched()->count(),
            'scanned_by' => $scan->rider?->name,
            // The rider who had the parcel when it was scanned.
            'parcel_rider' => $scan->parcelRider?->name,
            'items' => $scan->items->map(fn (StockScanItem $item) => [
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => abs($item->quantity),
                'matched' => $item->isMatched(),
                'stock_after' => $item->stock_after,
            ])->values()->all(),
            'parcel' => $scan->shipment ? self::parcel($scan->shipment) : null,
        ];
    }

    public static function parcel(Shipment $shipment): array
    {
        $shipment->loadMissing('order');
        $receiver = $shipment->receiverDetails();

        return [
            'id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'order_number' => $shipment->order?->order_number,
            'courier' => $shipment->courier,
            'courier_label' => self::courierLabel((string) $shipment->courier),
            'status' => $shipment->status,
            'status_label' => $shipment->status_label,
            'receiver_name' => $receiver['name'],
            'city' => $receiver['city'],
        ];
    }

    public static function courierLabel(string $courier): string
    {
        return match ($courier) {
            'jnt_express' => 'J&T Express',
            'imile' => 'iMile',
            'logestechs' => 'LogesTechs',
            'ksadrop_express' => 'KSA Express',
            default => $courier,
        };
    }
}

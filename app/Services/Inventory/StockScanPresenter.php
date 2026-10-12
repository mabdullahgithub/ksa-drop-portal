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
            'shortages' => self::shortages($scan),
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

    /**
     * The products a parcel going out needed more of than there was: what it
     * needed, and what was in stock before it. The parcel still went and the
     * stock is left below zero — this is for the manager to see, not a
     * refusal.
     *
     * @return list<array{name: string, sku: ?string, needed: int, in_stock: int}>
     */
    public static function shortages(StockScan $scan): array
    {
        if ($scan->direction !== StockScan::OUT) {
            return [];
        }

        return $scan->items
            ->filter->isMatched()
            // The same product on two lines is one count to fall short of.
            ->groupBy(fn (StockScanItem $item) => $item->product_id !== null ? "product:{$item->product_id}" : "client:{$item->client_product_id}")
            ->filter(fn ($lines) => $lines->min('stock_after') < 0)
            ->map(fn ($lines) => [
                'name' => $lines->first()->name,
                'sku' => $lines->first()->sku,
                'needed' => (int) abs($lines->sum('quantity')),
                // What the first line found. Already below zero is nothing on the shelf.
                'in_stock' => max(0, $lines->first()->stock_after - $lines->first()->quantity),
            ])
            ->values()
            ->all();
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

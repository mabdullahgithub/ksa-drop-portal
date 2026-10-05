<?php

namespace App\Services\Inventory;

use App\Models\ClientProduct;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\StockScan;
use App\Models\StockScanItem;
use App\Services\Shipping\ShipmentEventRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one path stock takes when a parcel crosses the warehouse door.
 *
 * An inventory manager scans a parcel OUT as it leaves and IN as it comes
 * back. OUT takes the order's items off stock; IN puts them back. Whichever
 * courier carries it, and — for IN — whatever became of the delivery.
 *
 * Stock is all it changes. The parcel's status and the rider holding it are
 * left alone: a KSA Express rider still scans the parcel in their own app to
 * take it (ShipmentEventRecorder).
 *
 * A parcel is either in or out, so two scans the same way in a row are
 * refused — that is what keeps a double scan from counting stock twice. A
 * wrong scan is put right by scanning the parcel the other way.
 */
class StockScanRecorder
{
    /**
     * @param  array{client_uuid: string, entry_method?: ?string, occurred_at?: ?string}  $input
     *
     * @throws StockScanRefused
     */
    public function record(Shipment $shipment, Rider $manager, string $direction, array $input): StockScan
    {
        // A retry of a scan that already went through (the response was lost
        // on a weak signal, or a double tap) must not move stock twice.
        if ($existing = $this->alreadyRecorded($input['client_uuid'], $manager)) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($shipment, $manager, $direction, $input) {
                $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
                $last = $shipment->stockScans()->with('items')->first();

                if ($reason = $this->refusal($shipment, $direction, $last)) {
                    throw new StockScanRefused($reason, $this->refusalMessage($reason, $shipment), $last);
                }

                // Coming back after going out: exactly what went out, so the
                // pair leaves stock where it started even if the order was
                // edited or re-synced in between.
                $lines = $direction === StockScan::IN && $last
                    ? $this->linesOf($last)
                    : $this->linesFor($shipment);

                $scan = StockScan::create([
                    'shipment_id' => $shipment->id,
                    'order_id' => $shipment->order_id,
                    'direction' => $direction,
                    'rider_id' => $manager->id,
                    'parcel_rider_id' => $shipment->rider_id,
                    'pieces' => array_sum(array_column($lines, 'quantity')),
                    'entry_method' => $input['entry_method'] ?? null,
                    'occurred_at' => ShipmentEventRecorder::occurredAt($input['occurred_at'] ?? null),
                    'client_uuid' => $input['client_uuid'],
                ]);

                $sign = $direction === StockScan::OUT ? -1 : 1;

                $scan->setRelation('items', collect($lines)->map(fn (array $line) => $scan->items()->create([
                    'product_id' => $line['target'] instanceof Product ? $line['target']->id : null,
                    'client_product_id' => $line['target'] instanceof ClientProduct ? $line['target']->id : null,
                    'name' => $line['name'],
                    'sku' => $line['sku'],
                    'quantity' => $sign * $line['quantity'],
                    'stock_after' => $this->move($line['target'], $sign * $line['quantity']),
                ])));

                return $scan;
            });
        } catch (UniqueConstraintViolationException $e) {
            // The same scan raced in twice; the other request recorded it.
            return $this->alreadyRecorded($input['client_uuid'], $manager) ?? throw $e;
        }
    }

    /**
     * Why this parcel can't be scanned this way right now, or null when it can.
     *
     * IN takes any parcel that isn't already in. OUT also refuses a parcel
     * whose delivery is over (delivered, returned, cancelled, failed): that
     * is an old label, and it must not leave again.
     */
    private function refusal(Shipment $shipment, string $direction, ?StockScan $last): ?string
    {
        if ($last?->direction === $direction) {
            return $direction === StockScan::OUT ? 'already_out' : 'already_in';
        }

        if ($direction === StockScan::OUT && $shipment->status_enum->isTerminal()) {
            return 'finished';
        }

        return null;
    }

    /**
     * What an earlier scan moved, to move it back.
     *
     * @return list<array{name: string, sku: ?string, quantity: int, target: Product|ClientProduct|null}>
     */
    private function linesOf(StockScan $scan): array
    {
        return $scan->items->map(fn (StockScanItem $item) => [
            'name' => $item->name,
            'sku' => $item->sku,
            'quantity' => abs($item->quantity),
            // A product removed since still gets its units back: it may be restored.
            'target' => match (true) {
                $item->product_id !== null => Product::withTrashed()->find($item->product_id),
                $item->client_product_id !== null => ClientProduct::withTrashed()->find($item->client_product_id),
                default => null,
            },
        ])->all();
    }

    /**
     * The parcel's contents: the order's items that ship, each with the
     * product whose stock it counts against (null when it matches none).
     *
     * @return list<array{name: string, sku: ?string, quantity: int, target: Product|ClientProduct|null}>
     */
    private function linesFor(Shipment $shipment): array
    {
        // In the order's own order, so the list reads like the packing slip.
        $order = $shipment->order()->with(['items' => fn ($q) => $q->orderBy('id')])->first();

        if (! $order) {
            return [];
        }

        return $order->items
            ->filter(fn (OrderItem $item) => $item->lineitem_requires_shipping !== false && $item->lineitem_quantity > 0)
            ->map(fn (OrderItem $item) => [
                'name' => Str::limit(trim($item->lineitem_name . ($item->variant_name ? ' - ' . $item->variant_name : '')), 497),
                'sku' => filled($item->lineitem_sku) ? mb_substr(trim($item->lineitem_sku), 0, 255) : null,
                'quantity' => (int) $item->lineitem_quantity,
                'target' => $this->productFor($item, $order->client_id),
            ])
            ->values()
            ->all();
    }

    /**
     * The product an order item counts against. An order made in the portal
     * names it; a Shopify order only carries the SKU, which is looked for in
     * the client's own stock first and then in the catalogue.
     */
    private function productFor(OrderItem $item, ?int $clientId): Product|ClientProduct|null
    {
        if ($item->client_product_id && ($linked = ClientProduct::find($item->client_product_id))) {
            return $linked;
        }

        if ($item->product_id && ($linked = Product::find($item->product_id))) {
            return $linked;
        }

        $sku = trim((string) $item->lineitem_sku);

        if ($sku === '') {
            return null;
        }

        $own = $clientId
            ? ClientProduct::where('client_id', $clientId)->where('sku', $sku)->oldest('id')->first()
            : null;

        return $own ?? Product::where('variant_sku', $sku)->oldest('id')->first();
    }

    /**
     * Add to (or, below zero, take from) a product's stock in one statement,
     * so two scans at once can't lose each other's change. Returns the stock
     * it leaves — which may be below zero: a hand-over is never held up
     * because the count was already wrong.
     */
    private function move(Product|ClientProduct|null $target, int $by): ?int
    {
        if ($target === null) {
            return null;
        }

        $column = $target instanceof Product ? 'variant_inventory_qty' : 'quantity';
        $row = fn () => $target->newQueryWithoutScopes()->whereKey($target->getKey());

        $row()->increment($column, $by);

        return (int) $row()->value($column);
    }

    private function alreadyRecorded(string $clientUuid, Rider $manager): ?StockScan
    {
        $scan = StockScan::with('items')->where('client_uuid', $clientUuid)->first();

        if ($scan && (int) $scan->rider_id !== $manager->id) {
            throw new StockScanRefused('not_allowed', 'This scan was already recorded by someone else.');
        }

        return $scan;
    }

    private function refusalMessage(string $reason, Shipment $shipment): string
    {
        return match ($reason) {
            'already_out' => 'This parcel was already scanned OUT.',
            'already_in' => 'This parcel was already scanned IN.',
            'finished' => "This parcel is {$shipment->status_label}. Do not hand it out.",
            default => 'This scan is not allowed for this parcel right now.',
        };
    }
}

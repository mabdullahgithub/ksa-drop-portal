<?php

namespace App\Services\Inventory;

use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockScan;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Warnings about a fulfilment client's stock running out.
 *
 * Two things read from it: the warning cards in the corner of the portal and the admin
 * side (a product is down to its last 3, 2, 1 — or has none left), and the
 * "No stock" mark on an order whose product has none left, so the client sees
 * why it is waiting and the team does not try to process it.
 *
 * Nothing is stored. Both are worked out from the stock as it stands, so they
 * clear by themselves the moment stock is added back.
 */
class StockAlerts
{
    /** The warning starts at this many units left. */
    public const LOW_AT = 3;

    /** The most products the admin is warned about at once; past this it is a stock-take, not a warning. */
    private const STAFF_LIMIT = 200;

    /**
     * A parcel in one of these states has already left the warehouse, so its
     * order no longer waits on stock.
     */
    private const LEFT_THE_WAREHOUSE = [
        ShipmentStatus::IN_TRANSIT,
        ShipmentStatus::OUT_FOR_DELIVERY,
        ShipmentStatus::ATTEMPT_FAIL,
        ShipmentStatus::EXCEPTION,
        ShipmentStatus::DELIVERED,
        ShipmentStatus::RETURNED,
    ];

    /**
     * The client's own products that are low or out, emptiest first.
     *
     * @return list<array{id: int, name: string, sku: ?string, product_code: string, left: int}>
     */
    public function forClient(Client $client): array
    {
        if (! $client->is_fulfilment) {
            return [];
        }

        return $this->lowProducts()
            ->where('client_id', $client->id)
            ->get()
            ->map(fn (ClientProduct $product) => $this->present($product))
            ->all();
    }

    /**
     * Every fulfilment client's low or out products, for the team.
     *
     * @return list<array{id: int, name: string, sku: ?string, product_code: string, left: int, client: array{id: int, company_name: string, client_id: string}}>
     */
    public function forStaff(): array
    {
        return $this->lowProducts()
            ->with('client:id,company_name,short_id,client_types')
            ->limit(self::STAFF_LIMIT)
            ->get()
            // A deleted client's stock is nobody's to restock.
            ->filter(fn (ClientProduct $product) => $product->client?->is_fulfilment)
            ->map(fn (ClientProduct $product) => $this->present($product) + [
                'client' => [
                    'id' => $product->client->id,
                    'company_name' => $product->client->company_name,
                    'client_id' => $product->client->client_id,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Mark each order that is waiting on a product with no stock: sets
     * `out_of_stock_items` to the items concerned (empty when there are none).
     *
     * Expects `items` and `latestShipment` to be loaded.
     *
     * @param  iterable<Order>  $orders
     */
    public function flagOrders(iterable $orders): void
    {
        $orders = collect($orders)->each(fn (Order $order) => $order->setAttribute('out_of_stock_items', []));

        $waiting = $orders->filter(fn (Order $order) => $order->client_id && $this->awaitsStock($order))->keyBy('id');

        if ($waiting->isEmpty()) {
            return;
        }

        $products = $this->productsFor($waiting);

        if ($products->isEmpty()) {
            return;
        }

        $flagged = $waiting
            ->map(fn (Order $order) => $order->items
                ->filter(fn (OrderItem $item) => $item->lineitem_requires_shipping !== false && $item->lineitem_quantity > 0)
                ->filter(fn (OrderItem $item) => ($product = $this->productFor($item, $order->client_id, $products)) && $this->isOut($product))
                ->map(fn (OrderItem $item) => [
                    'item_id' => $item->id,
                    'name' => $item->lineitem_name,
                    'sku' => $item->lineitem_sku,
                ])
                ->values()
                ->all())
            ->filter();

        if ($flagged->isEmpty()) {
            return;
        }

        // Scanned OUT: its units are already off the shelf and on their way —
        // quite possibly the very units that took the product to zero.
        $out = StockScan::query()
            ->whereIn('id', StockScan::query()->selectRaw('max(id)')->whereIn('order_id', $flagged->keys())->groupBy('order_id'))
            ->where('direction', StockScan::OUT)
            ->pluck('order_id')
            ->flip();

        $orders->each(function (Order $order) use ($flagged, $out) {
            if ($flagged->has($order->id) && ! $out->has($order->id)) {
                $order->setAttribute('out_of_stock_items', $flagged[$order->id]);
            }
        });
    }

    private function lowProducts(): Builder
    {
        return ClientProduct::query()
            // A rejected product is not held for the client, so there is nothing to run out of.
            ->where('verification_status', '!=', 'rejected')
            ->where(fn ($query) => $query->where('quantity', '<=', self::LOW_AT)->orWhere('is_out_of_stock', true))
            ->orderBy('quantity')
            ->orderBy('name');
    }

    private function present(ClientProduct $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'product_code' => $product->product_code,
            'left' => $this->isOut($product) ? 0 : (int) $product->quantity,
        ];
    }

    /**
     * Stock can be counted below zero (a hand-over is never held up by a
     * wrong count), and the team can mark a product out by hand at review.
     */
    private function isOut(ClientProduct $product): bool
    {
        return $product->quantity <= 0 || $product->is_out_of_stock;
    }

    /**
     * Still to be picked off the shelf: not finished or cancelled, and its
     * parcel (if one is booked) has not left the warehouse.
     */
    private function awaitsStock(Order $order): bool
    {
        if (in_array($order->fulfillment_status, ['fulfilled', 'cancelled'], true)) {
            return false;
        }

        $status = $order->latestShipment?->status_enum;

        return $status === null || ! in_array($status, self::LEFT_THE_WAREHOUSE, true);
    }

    /**
     * The client products these orders' items could count against, in one query.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, ClientProduct>  oldest first
     */
    private function productsFor(Collection $orders): Collection
    {
        $items = $orders->flatMap(fn (Order $order) => $order->items);

        $ids = $items->pluck('client_product_id')->filter()->unique()->values();
        $skus = $items->map(fn (OrderItem $item) => trim((string) $item->lineitem_sku))->filter(fn ($sku) => $sku !== '')->unique()->values();

        if ($ids->isEmpty() && $skus->isEmpty()) {
            return collect();
        }

        return ClientProduct::query()
            ->where(fn ($query) => $query
                ->whereIn('id', $ids)
                ->orWhere(fn ($own) => $own->whereIn('client_id', $orders->pluck('client_id')->unique())->whereIn('sku', $skus)))
            ->orderBy('id')
            ->get(['id', 'client_id', 'sku', 'quantity', 'is_out_of_stock']);
    }

    /**
     * The client product an order item counts against — the same choice
     * StockScanRecorder::productFor() makes when the parcel is scanned: the
     * product the order names, else the client's oldest one with that SKU.
     * An item tied to the catalogue is not the client's stock.
     *
     * @param  Collection<int, ClientProduct>  $products  oldest first
     */
    private function productFor(OrderItem $item, int $clientId, Collection $products): ?ClientProduct
    {
        if ($item->client_product_id && ($linked = $products->firstWhere('id', $item->client_product_id))) {
            return $linked;
        }

        if ($item->product_id) {
            return null;
        }

        $sku = $this->sku($item->lineitem_sku);

        if ($sku === '') {
            return null;
        }

        return $products->first(fn (ClientProduct $product) => (int) $product->client_id === $clientId && $this->sku($product->sku) === $sku);
    }

    /**
     * A SKU as the database compares it: case and outer spaces don't count.
     */
    private function sku(?string $sku): string
    {
        return mb_strtolower(trim((string) $sku));
    }
}

<?php

namespace App\Services\Chat;

use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;

/**
 * Orders and inventory products attached to a message, so both sides know
 * exactly which one is meant. The client shares their own; the team shares
 * that client's (each kind needs the permission to view it).
 *
 * Only ids come from the browser. Each is looked up among that client's own
 * records, and what the card shows is saved with the message as it was then:
 * "it said 3 in stock when I wrote" stays true after the stock moves.
 */
class ChatSharedItems
{
    public const ORDER = 'order';

    public const PRODUCT = 'product';

    /** My Inventory is for fulfilment clients who have it switched on. */
    public static function hasInventory(Client $client): bool
    {
        return $client->is_fulfilment && in_array('inventory', $client->portal_features ?? [], true);
    }

    /** Whether there are products to share: the team may for any fulfilment client. */
    public static function canShareProducts(Client $client, bool $byTeam): bool
    {
        return $byTeam ? $client->is_fulfilment : self::hasInventory($client);
    }

    /**
     * @param  list<int>  $orderIds
     * @param  list<int>  $productIds
     * @return list<array<string, mixed>>|null
     */
    public static function snapshot(Client $client, array $orderIds, array $productIds, bool $byTeam = false): ?array
    {
        $orderIds = array_values(array_unique(array_map('intval', $orderIds)));
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if ($orderIds === [] && $productIds === []) {
            return null;
        }

        if ($productIds !== [] && ! self::canShareProducts($client, $byTeam)) {
            abort(422, 'Inventory is not available on this account.');
        }

        $orders = $orderIds === [] ? collect() : Order::query()
            ->where('client_id', $client->id)
            ->whereIn('id', $orderIds)
            ->get(['id', 'order_number', 'fulfillment_status', 'total', 'currency', 'created_at']);

        $products = $productIds === [] ? collect() : ClientProduct::query()
            ->where('client_id', $client->id)
            ->whereIn('id', $productIds)
            ->with('images')
            ->get();

        if ($orders->count() !== count($orderIds) || $products->count() !== count($productIds)) {
            abort(422, 'One of those orders or products does not belong to this client.');
        }

        return [
            ...$orders->map(fn (Order $order) => [
                'type' => self::ORDER,
                'id' => $order->id,
                'title' => (string) $order->order_number,
                'status' => $order->fulfillment_status,
                'total' => $order->total === null ? null : (string) $order->total,
                'currency' => $order->currency,
                'date' => $order->created_at?->toIso8601String(),
            ])->values()->all(),
            ...$products->map(fn (ClientProduct $product) => [
                'type' => self::PRODUCT,
                'id' => $product->id,
                'title' => $product->name,
                'code' => $product->product_code,
                'sku' => $product->sku,
                'quantity' => (int) $product->quantity,
                'out_of_stock' => (bool) $product->is_out_of_stock,
                'image_path' => $product->images->first()?->path,
            ])->values()->all(),
        ];
    }

    /**
     * Saved cards as the browser receives them: the picture as a URL.
     *
     * @return list<array<string, mixed>>
     */
    public static function present(?array $items): array
    {
        return array_map(function (array $item) {
            if ($item['type'] === self::PRODUCT) {
                $path = $item['image_path'] ?? null;
                unset($item['image_path']);
                $item['image_url'] = $path ? Storage::disk('public')->url($path) : null;
            }

            return $item;
        }, $items ?? []);
    }

    /** The client's orders for the picker. @return list<array<string, mixed>> */
    public static function orderOptions(Client $client, string $term): array
    {
        return Order::query()
            ->where('client_id', $client->id)
            ->when(trim($term), fn ($query, $t) => $query->where('order_number', 'like', '%' . addcslashes($t, '%_\\') . '%'))
            ->latest('id')
            ->limit(20)
            ->get(['id', 'order_number', 'fulfillment_status', 'total', 'currency', 'created_at'])
            ->map(fn (Order $order) => [
                'type' => self::ORDER,
                'id' => $order->id,
                'title' => (string) $order->order_number,
                'status' => $order->fulfillment_status,
                'total' => $order->total === null ? null : (string) $order->total,
                'currency' => $order->currency,
                'date' => $order->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** The client's products for the picker. @return list<array<string, mixed>> */
    public static function productOptions(Client $client, string $term): array
    {
        $products = ClientProduct::query()
            ->where('client_id', $client->id)
            ->when(trim($term), fn ($query, $t) => $query->search(addcslashes($t, '%_\\')))
            ->with('images')
            ->latest('id')
            ->limit(20)
            ->get();

        return $products->map(fn (ClientProduct $product) => [
            'type' => self::PRODUCT,
            'id' => $product->id,
            'title' => $product->name,
            'code' => $product->product_code,
            'sku' => $product->sku,
            'quantity' => (int) $product->quantity,
            'out_of_stock' => (bool) $product->is_out_of_stock,
            'image_url' => $product->images->first()?->url,
        ])->all();
    }
}

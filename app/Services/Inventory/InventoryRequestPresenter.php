<?php

namespace App\Services\Inventory;

use App\Models\ClientProduct;
use App\Models\InventoryRequest;

/**
 * An inventory request as the Requests page and the client's My Inventory
 * page show it.
 */
class InventoryRequestPresenter
{
    /** What the product form calls each field a details request can change. */
    public const FIELD_LABELS = [
        'name' => 'Name',
        'sku' => 'SKU',
        'description' => 'Description',
        'unit_price' => 'Unit price',
        'notes' => 'Notes',
    ];

    /**
     * Lists should eager-load these. The product comes with the recycle bin
     * included: a decided request stays in the history after its product is
     * deleted.
     */
    public static function relations(): array
    {
        return [
            'product' => fn ($query) => $query->withTrashed()->with('images'),
            'client',
            'images',
            'requestedBy:id,name',
            'reviewedBy:id,name',
        ];
    }

    /**
     * @param  bool  $forTeam  Adds what only the team should see: who on the
     *                         team decided it, and the checks made before approving.
     */
    public static function request(InventoryRequest $request, bool $forTeam = true): array
    {
        $request->loadMissing(self::relations());

        $product = $request->product;
        $changes = $request->changes ?? [];
        $fields = array_intersect_key($changes, self::FIELD_LABELS);

        return [
            'id' => $request->id,
            'type' => $request->type,
            'status' => $request->status,
            'revision' => $request->revision,
            'created_at' => $request->created_at?->toIso8601String(),
            'updated_at' => $request->updated_at?->toIso8601String(),
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'rejection_reason' => $request->rejection_reason,
            'requested_by' => $request->requestedBy?->name,

            'client' => $request->client ? [
                'id' => $request->client->id,
                'company_name' => $request->client->company_name,
            ] : null,

            'product' => $product ? [
                'id' => $product->id,
                'product_code' => $product->product_code,
                'name' => $product->name,
                'sku' => $product->sku,
                'quantity' => (int) $product->quantity,
                'is_out_of_stock' => (bool) $product->is_out_of_stock,
                'deleted' => $product->trashed(),
                'images' => $product->images->map(fn ($image) => ['id' => $image->id, 'url' => $image->url])->values()->all(),
            ] : null,

            // Stock.
            'requested_quantity' => $request->requested_quantity,
            'stock_at_request' => $request->stock_at_request,
            'change' => $request->type === InventoryRequest::STOCK ? $request->requestedChange() : null,
            'received_quantity' => $request->received_quantity,
            'stock_before' => $request->stock_before,
            'stock_after' => $request->stock_after,

            // Details: the fields asked for, what they held then, and what
            // the product holds now.
            'changes' => (object) $fields,
            'original' => (object) array_intersect_key($request->original ?? [], self::FIELD_LABELS),
            'current' => (object) ($product ? self::current($product, array_keys($fields)) : []),
            'remove_image_ids' => array_values($changes['remove_image_ids'] ?? []),
            'images' => $request->images->map(fn ($image) => ['id' => $image->id, 'url' => $image->url])->values()->all(),
        ] + ($forTeam ? [
            'reviewed_by' => $request->reviewedBy?->name,
            'sku_in_use' => $product && $request->isPending() && isset($fields['sku']) && self::skuInUse($product, $fields['sku']),
        ] : []);
    }

    /**
     * One line saying what was asked, for notifications and emails.
     */
    public static function summary(InventoryRequest $request): string
    {
        if ($request->type === InventoryRequest::STOCK) {
            $change = $request->requestedChange();

            return "stock from {$request->stock_at_request} to {$request->requested_quantity} ("
                . ($change > 0 ? '+' : '') . $change . ')';
        }

        $changes = $request->changes ?? [];
        $parts = array_values(array_intersect_key(self::FIELD_LABELS, $changes));

        if (! empty($changes['remove_image_ids']) || $request->images()->exists()) {
            $parts[] = 'Images';
        }

        return 'details (' . implode(', ', $parts) . ')';
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, ?string>
     */
    private static function current(ClientProduct $product, array $fields): array
    {
        $current = [];

        foreach ($fields as $field) {
            $current[$field] = InventoryRequestRecorder::normalise($field, $product->{$field});
        }

        return $current;
    }

    /**
     * Another product of the same client already carries this SKU. Orders
     * find their stock by SKU and the oldest product wins, so approving
     * would point orders at the wrong one.
     */
    private static function skuInUse(ClientProduct $product, ?string $sku): bool
    {
        return $sku !== null && ClientProduct::acrossClients()
            ->where('client_id', $product->client_id)
            ->where('sku', $sku)
            ->whereKeyNot($product->id)
            ->exists();
    }
}

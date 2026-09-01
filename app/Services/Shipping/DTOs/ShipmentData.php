<?php

namespace App\Services\Shipping\DTOs;

use App\Models\Order;
use App\Models\Warehouse;

class ShipmentData
{
    public function __construct(
        public readonly array $sender,
        public readonly array $receiver,
        public readonly string $txlogisticId,
        public readonly float $weight,
        public readonly ?float $length,
        public readonly ?float $width,
        public readonly ?float $height,
        public readonly string $serviceType,
        public readonly string $itemDescription,
        public readonly int $quantity,
        public readonly string $goodsType = 'ITN1',
        public readonly array $items = [],
        public readonly ?string $remark = null,
        public readonly int $operateType = 1,
        public readonly ?string $billCode = null,
        public readonly float $codAmount = 0.0,
        public readonly string $codCurrency = 'SAR',
    ) {}

    public static function fromOrder(Order $order, Warehouse $warehouse, array $options = []): self
    {
        $sender = [
            'name'         => $warehouse->contact_name,
            // LogesTechs prints a separate business name on the waybill; the
            // other drivers ignore this key.
            'businessName' => $warehouse->name ?? '',
            'phone'        => $warehouse->phone,
            'province'     => $warehouse->province,
            'city'         => $warehouse->city,
            'area'         => $warehouse->area ?? '',
            'address'      => $warehouse->address,
            'postCode'     => $warehouse->post_code,
            'countryCode'  => $warehouse->country_code,
            'shortAddress' => $warehouse->short_address ?? '',
        ];

        $receiver = [
            'name'         => $options['receiver_name'] ?? $order->shipping_name ?? $order->customer_name,
            'phone'        => $options['receiver_phone'] ?? $order->shipping_phone ?? $order->customer_phone,
            'phone2'       => $options['receiver_phone2'] ?? '',
            'province'     => $options['receiver_province'] ?? $order->shipping_province ?? '',
            'city'         => $options['receiver_city'] ?? $order->shipping_city ?? '',
            'area'         => $options['receiver_area'] ?? '',
            'address'      => $options['receiver_address'] ?? $order->shipping_address1 ?? '',
            'postCode'     => $options['receiver_post_code'] ?? $order->shipping_zip ?? '',
            'countryCode'  => $options['receiver_country_code'] ?? $order->shipping_country ?? 'KSA',
            // Saudi National Address short code (e.g. RDLC4305). Introduced for
            // iMile; LogesTechs sends the same value as `nationalAddress`.
            'shortAddress' => $options['receiver_short_address'] ?? '',
            // LogesTechs resolves the destination from a district ("village")
            // rather than free-text city. No order field maps to it, so these
            // only ever arrive as explicit options from the create dialog.
            // District names aren't unique, so the id is preferred when the
            // dialog resolved one against LogesTechs' district lookup.
            'village'      => $options['receiver_village'] ?? '',
            'villageId'    => $options['receiver_village_id'] ?? '',
        ];

        $itemDescription = $order->items->pluck('lineitem_name')->filter()->implode(', ') ?: 'Package';
        $quantity        = $order->items->sum('lineitem_quantity') ?: 1;

        $items = $order->items->map(fn ($item) => [
            'name'     => (string) ($item->lineitem_name ?? ''),
            'type'     => $options['goods_type'] ?? 'ITN4',
            'quantity' => (int) ($item->lineitem_quantity ?? 1),
            'value'    => (string) ($item->lineitem_price ?? '0'),
            'currency' => 'SAR',
        ])->values()->all();

        $remark = $options['remark'] ?? $order->note ?? null;
        if ($remark) {
            $remark = mb_substr((string) $remark, 0, 200);
        }

        // COD amount to collect on delivery. Only COD orders should carry a
        // collectable amount; prepaid orders collect nothing. Callers may
        // override explicitly via options['cod_amount']. Note: payment_method is
        // free-text across import sources, so detection lives in Order::isCashOnDelivery().
        if (array_key_exists('cod_amount', $options)) {
            $codAmount = (float) $options['cod_amount'];
        } elseif ($order->isCashOnDelivery()) {
            $codAmount = (float) ($order->total ?? 0);
        } else {
            $codAmount = 0.0;
        }

        return new self(
            sender:          $sender,
            receiver:        $receiver,
            txlogisticId:    $options['txlogistic_id'] ?? (string) $order->order_number,
            weight:          (float) ($options['weight'] ?? 0.5),
            length:          isset($options['length']) ? (float) $options['length'] : null,
            width:           isset($options['width']) ? (float) $options['width'] : null,
            height:          isset($options['height']) ? (float) $options['height'] : null,
            serviceType:     $options['service_type'] ?? '02',
            itemDescription: $itemDescription,
            quantity:        $quantity,
            goodsType:       $options['goods_type'] ?? 'ITN1',
            items:           $items,
            remark:          $remark,
            operateType:     $options['operate_type'] ?? 1,
            billCode:        $options['bill_code'] ?? null,
            codAmount:       $codAmount,
            codCurrency:     $options['cod_currency'] ?? ($order->currency ?? 'SAR'),
        );
    }
}

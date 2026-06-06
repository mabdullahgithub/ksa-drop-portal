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
    ) {}

    public static function fromOrder(Order $order, Warehouse $warehouse, array $options = []): self
    {
        $sender = [
            'name' => $warehouse->contact_name,
            'phone' => $warehouse->phone,
            'province' => $warehouse->province,
            'city' => $warehouse->city,
            'area' => $warehouse->area ?? '',
            'address' => $warehouse->address,
            'postCode' => $warehouse->post_code,
            'countryCode' => $warehouse->country_code,
        ];

        $receiver = [
            'name' => $options['receiver_name'] ?? $order->shipping_name ?? $order->customer_name,
            'phone' => $options['receiver_phone'] ?? $order->shipping_phone ?? $order->customer_phone,
            'province' => $options['receiver_province'] ?? $order->shipping_province ?? '',
            'city' => $options['receiver_city'] ?? $order->shipping_city ?? '',
            'area' => $options['receiver_area'] ?? '',
            'address' => $options['receiver_address'] ?? $order->shipping_address1 ?? '',
            'postCode' => $options['receiver_post_code'] ?? $order->shipping_zip ?? '',
            'countryCode' => $options['receiver_country_code'] ?? $order->shipping_country ?? 'KSA',
        ];

        $itemDescription = $order->items->pluck('lineitem_name')->filter()->implode(', ') ?: 'Package';
        $quantity = $order->items->sum('lineitem_quantity') ?: 1;

        return new self(
            sender: $sender,
            receiver: $receiver,
            txlogisticId: $options['txlogistic_id'] ?? 'KSD-' . $order->order_number . '-' . time(),
            weight: $options['weight'] ?? 0.5,
            length: $options['length'] ?? null,
            width: $options['width'] ?? null,
            height: $options['height'] ?? null,
            serviceType: $options['service_type'] ?? '02',
            itemDescription: $itemDescription,
            quantity: $quantity,
        );
    }
}

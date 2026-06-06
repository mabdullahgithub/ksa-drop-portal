<?php

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ShipmentDeliveredNotification extends Notification
{
    use Queueable;

    public function __construct(private Shipment $shipment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $orderNumber = $this->shipment->order?->order_number ?? $this->shipment->order_id;

        return [
            'title' => 'Shipment Delivered',
            'message' => "Order #{$orderNumber} was delivered (tracking {$this->shipment->tracking_number}).",
            'icon' => 'truck',
            'action_url' => "/orders/{$this->shipment->order_id}",
        ];
    }
}

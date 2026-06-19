<?php

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ShipmentExceptionNotification extends Notification
{
    use Queueable;

    public function __construct(private Shipment $shipment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $orderNumber = $this->shipment->order?->order_number ?? $this->shipment->order_id;

        return [
            'title'      => 'Shipment Exception Escalated',
            'message'    => "Order #{$orderNumber} has a shipment exception requiring attention (tracking {$this->shipment->tracking_number}).",
            'icon'       => 'alert-triangle',
            'action_url' => "/orders/{$this->shipment->order_id}",
            'note'       => $this->shipment->exception_note,
        ];
    }
}

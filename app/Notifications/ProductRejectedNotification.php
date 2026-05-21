<?php

namespace App\Notifications;

use App\Models\ClientProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(private ClientProduct $product) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $message = "Your product \"{$this->product->name}\" ({$this->product->product_code}) has been rejected.";

        if ($this->product->rejection_reason) {
            $message .= ' Reason: ' . $this->product->rejection_reason;
        }

        return [
            'title'      => 'Product Rejected',
            'message'    => $message,
            'icon'       => 'shield-x',
            'action_url' => '/portal/inventory',
        ];
    }
}

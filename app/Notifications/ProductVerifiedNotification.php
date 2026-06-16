<?php

namespace App\Notifications;

use App\Mail\Clients\ProductVerifiedMail;
use App\Models\ClientProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductVerifiedNotification extends Notification
{
    use Queueable;

    public function __construct(private ClientProduct $product) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $emailService = app(\App\Services\EmailService::class);
        if ($emailService->isEnabled()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): ProductVerifiedMail
    {
        return (new ProductVerifiedMail($this->product))
            ->to($notifiable->email);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'      => 'Product Verified',
            'message'    => "Your product \"{$this->product->name}\" ({$this->product->product_code}) has been verified and is now active in your inventory.",
            'icon'       => 'shield-check',
            'action_url' => '/portal/inventory',
        ];
    }
}

<?php

namespace App\Notifications;

use App\Mail\Clients\ProductSubmittedAdminMail;
use App\Models\Client;
use App\Models\ClientProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ClientProduct $product,
        private Client $client
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $emailService = app(\App\Services\EmailService::class);
        if ($emailService->isEnabled()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): ProductSubmittedAdminMail
    {
        return (new ProductSubmittedAdminMail($this->product, $this->client))
            ->to($notifiable->email);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'      => 'New Product Awaiting Verification',
            'message'    => "{$this->client->company_name} submitted \"{$this->product->name}\" ({$this->product->product_code}) for verification.",
            'icon'       => 'package',
            'action_url' => "/client/{$this->client->id}",
        ];
    }
}

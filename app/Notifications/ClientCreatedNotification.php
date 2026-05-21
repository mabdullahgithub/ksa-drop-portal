<?php

namespace App\Notifications;

use App\Mail\Clients\ClientCreatedAdminMail;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClientCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(private Client $client) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $emailService = app(\App\Services\EmailService::class);
        if ($emailService->isEnabled()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): ClientCreatedAdminMail
    {
        return new ClientCreatedAdminMail($this->client);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'      => 'New Client Registered',
            'message'    => "{$this->client->company_name} ({$this->client->client_id}) has joined as {$this->client->type_label}.",
            'icon'       => 'user-circle',
            'action_url' => "/client/{$this->client->id}",
        ];
    }
}

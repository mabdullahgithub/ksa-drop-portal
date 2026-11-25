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
        return (new ClientCreatedAdminMail($this->client))
            ->to($notifiable->email);
    }

    public function toArray(object $notifiable): array
    {
        if ($this->client->status === Client::STATUS_PENDING) {
            return [
                'title'      => 'New Client Signup',
                'message'    => "{$this->client->company_name} ({$this->client->client_id}) signed up as {$this->client->type_label} and is waiting for approval.",
                'icon'       => 'user-circle',
                // Straight to the queue for whoever can open it.
                'action_url' => $notifiable->can('view requests') && $notifiable->can('view signup requests')
                    ? '/requests?tab=signups'
                    : "/client/{$this->client->id}",
            ];
        }

        return [
            'title'      => 'New Client Registered',
            'message'    => "{$this->client->company_name} ({$this->client->client_id}) has joined as {$this->client->type_label}.",
            'icon'       => 'user-circle',
            'action_url' => "/client/{$this->client->id}",
        ];
    }
}

<?php

namespace App\Notifications;

use App\Mail\Clients\ClientStatusChangedMail;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClientStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Client $client,
        private string $oldStatus,
        private string $newStatus
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

    public function toMail(object $notifiable): ClientStatusChangedMail
    {
        return (new ClientStatusChangedMail($this->client, $this->oldStatus, $this->newStatus))
            ->to($notifiable->email);
    }

    public function toArray(object $notifiable): array
    {
        $messages = [
            'active'    => 'Your account is now active. You have full access to the portal.',
            'inactive'  => 'Your account has been deactivated. Please contact support if you have questions.',
            'suspended' => 'Your account has been suspended. Please contact support to resolve this.',
        ];

        return [
            'title'      => 'Account Status Updated',
            'message'    => $messages[$this->newStatus] ?? "Your account status has been changed to {$this->newStatus}.",
            'icon'       => 'user-check',
            'action_url' => '/portal',
        ];
    }
}

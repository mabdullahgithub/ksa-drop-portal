<?php

namespace App\Notifications;

use App\Mail\Clients\ClientStatusChangedMail;
use App\Mail\Signups\SignupApprovedMail;
use App\Mail\Signups\SignupRejectedMail;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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

    public function toMail(object $notifiable): Mailable
    {
        $mail = $this->signupDecisionMail()
            ?? new ClientStatusChangedMail($this->client, $this->oldStatus, $this->newStatus);

        return $mail->to($notifiable->email);
    }

    /**
     * A public signup's first decision has its own email, wherever it was
     * made (the Requests page or the Clients page). Null for any other change.
     */
    private function signupDecisionMail(): ?Mailable
    {
        $undecided = [Client::STATUS_PENDING, Client::STATUS_REJECTED];

        if ($this->client->signed_up_at === null || ! in_array($this->oldStatus, $undecided, true)) {
            return null;
        }

        return match ($this->newStatus) {
            'active' => new SignupApprovedMail($this->client),
            Client::STATUS_REJECTED => new SignupRejectedMail($this->client),
            default => null,
        };
    }

    public function toArray(object $notifiable): array
    {
        $messages = [
            'active'    => 'Your account is now active. You have full access to the portal.',
            'inactive'  => 'Your account has been deactivated. Please contact support if you have questions.',
            'suspended' => 'Your account has been suspended. Please contact support to resolve this.',
            'rejected'  => 'Your signup request was not approved. Please contact support.',
        ];

        return [
            'title'      => 'Account Status Updated',
            'message'    => $messages[$this->newStatus] ?? "Your account status has been changed to {$this->newStatus}.",
            'icon'       => 'user-check',
            'action_url' => '/portal',
        ];
    }
}

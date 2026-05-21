<?php

namespace App\Mail\Clients;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientStatusChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public string $oldStatus,
        public string $newStatus
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Account Status Has Been Updated',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clients.client-status-changed',
            with: [
                'client'    => $this->client,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'portalUrl' => config('app.url') . '/portal',
            ],
        );
    }
}

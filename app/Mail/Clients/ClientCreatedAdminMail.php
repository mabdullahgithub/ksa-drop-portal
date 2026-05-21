<?php

namespace App\Mail\Clients;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientCreatedAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Client $client) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Client Registered — ' . $this->client->company_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clients.client-created-admin',
            with: [
                'client'    => $this->client,
                'detailUrl' => config('app.url') . '/client/' . $this->client->id,
            ],
        );
    }
}

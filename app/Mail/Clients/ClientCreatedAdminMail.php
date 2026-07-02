<?php

namespace App\Mail\Clients;

use App\Mail\BaseMailable;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientCreatedAdminMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Client $client) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New client registered: ' . $this->client->company_name,
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clients.client-created-admin',
            text: 'emails.clients.client-created-admin-text',
            with: [
                'client'    => $this->client,
                'detailUrl' => config('app.url') . '/client/' . $this->client->id,
            ],
        );
    }
}

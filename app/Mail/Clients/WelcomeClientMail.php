<?php

namespace App\Mail\Clients;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class WelcomeClientMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public string $password
    ) {}

    public function headers(): Headers
    {
        return new Headers(
            messageId: null,
            references: [],
            text: [
                'Reply-To' => config('mail.from.address'),
                'X-Mailer' => config('app.name'),
            ],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your ' . config('app.name') . ' account is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clients.welcome',
            text: 'emails.clients.welcome-text',
            with: [
                'client' => $this->client,
                'password' => $this->password,
                'loginUrl' => config('app.url') . '/login',
            ],
        );
    }
}

<?php

namespace App\Mail\Clients;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeClientMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public string $password
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . config('app.name') . ' - Your Portal Access',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clients.welcome',
            with: [
                'client' => $this->client,
                'password' => $this->password,
                'loginUrl' => config('app.url') . '/login',
            ],
        );
    }
}

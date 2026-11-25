<?php

namespace App\Mail\Signups;

use App\Mail\BaseMailable;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the applicant when their signup is turned down, with the reason. */
class SignupRejectedMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Client $client) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on your ' . config('app.name') . ' signup request',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signups.rejected',
            text: 'emails.signups.rejected-text',
            with: [
                'client' => $this->client,
                'name' => $this->client->contact_person ?? $this->client->user->name,
                'loginUrl' => rtrim(config('app.url'), '/') . '/login',
                'recipient' => $this->client->user->email,
            ],
        );
    }
}

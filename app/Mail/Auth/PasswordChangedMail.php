<?php

namespace App\Mail\Auth;

use App\Mail\BaseMailable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangedMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $ipAddress = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your ' . config('app.name') . ' password has been updated',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.password-changed',
            text: 'emails.auth.password-changed-text',
            with: [
                'user'            => $this->user,
                'ipAddress'       => $this->ipAddress,
                'recipient'       => $this->user->email,
                'isSecurityEmail' => true,
            ],
        );
    }
}

<?php

namespace App\Mail\Auth;

use App\Mail\BaseMailable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorDisabledMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Security update: 2FA removed from your account',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.two-factor-disabled',
            text: 'emails.auth.two-factor-disabled-text',
            with: [
                'user'            => $this->user,
                'recipient'       => $this->user->email,
                'isSecurityEmail' => true,
            ],
        );
    }
}
